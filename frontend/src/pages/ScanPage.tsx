import { useCallback, useEffect, useRef, useState } from 'react'
import type { ChangeEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Camera, ImagePlus, RotateCcw, X } from 'lucide-react'
import { scan } from '../api/scan.ts'
import type { ScanResult } from '../api/scan.ts'
import { createItem } from '../api/items.ts'
import { ApiError } from '../api/client.ts'
import { resizeToBase64 } from '../lib/image.ts'
import { GARMENT_TYPES } from '../components/garmentGuide.ts'
import type { GarmentType } from '../components/garmentGuide.ts'
import { ScanFrameOverlay } from '../components/ScanFrameOverlay.tsx'
import { ItemForm } from './ItemFormPage.tsx'

type Phase = 'shoot' | 'result' | 'form'

const ITEM_TYPES = ['pull', 'tshirt', 'pantalon', 'jupe', 'robe', 'chaussure', 'sac', 'autre'] as const
const ITEM_CONDITIONS = [
  'neuf',
  'comme_neuf',
  'tres_bon',
  'bon',
  'a_reparer',
  'abime',
] as const

function scanFailureMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 503) {
      return 'L’analyse photo gratuite est temporairement indisponible. Réessayez plus tard ou saisissez l’article manuellement.'
    }
    if (error.status === 502) {
      return 'Le service d’analyse photo ne répond pas. Réessayez plus tard ou saisissez l’article manuellement.'
    }
    return error.message
  }

  return 'Une erreur inattendue est survenue pendant l’analyse. Vous pouvez saisir l’article manuellement.'
}

/**
 * Capture one item with the device camera, then let the user review or enter its details manually.
 */
export default function ScanPage() {
  const navigate = useNavigate()
  const videoRef = useRef<HTMLVideoElement>(null)
  const galleryInputRef = useRef<HTMLInputElement>(null)
  const cameraRequestIdRef = useRef(0)

  const [phase, setPhase] = useState<Phase>('shoot')
  const [guide, setGuide] = useState<GarmentType>('tshirt')
  const [cameraStream, setCameraStream] = useState<MediaStream | null>(null)
  const [cameraLoading, setCameraLoading] = useState(false)
  const [cameraError, setCameraError] = useState<string | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [scanning, setScanning] = useState(false)
  const [result, setResult] = useState<ScanResult | null>(null)
  const [scanError, setScanError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [unclearResult, setUnclearResult] = useState(false)

  useEffect(() => {
    const video = videoRef.current
    if (video !== null && cameraStream !== null) {
      video.srcObject = cameraStream
      void video.play().catch(() => {
        setCameraError('Impossible de démarrer l’aperçu caméra. Vérifiez les autorisations de votre navigateur.')
      })
    }

    return () => {
      cameraStream?.getTracks().forEach((track) => track.stop())
    }
  }, [cameraStream])

  function stopCamera() {
    cameraRequestIdRef.current += 1
    cameraStream?.getTracks().forEach((track) => track.stop())
    setCameraStream(null)
    setCameraLoading(false)
  }

  const startCamera = useCallback(async () => {
    setCameraError(null)
    setScanError(null)

    if (navigator.mediaDevices?.getUserMedia === undefined) {
      setCameraError('Caméra indisponible dans ce navigateur. Utilisez une connexion sécurisée (HTTPS ou localhost) ou choisissez une photo.')
      return
    }

    const requestId = ++cameraRequestIdRef.current
    setCameraLoading(true)
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: { facingMode: { ideal: 'environment' } },
      })
      if (requestId !== cameraRequestIdRef.current) {
        stream.getTracks().forEach((track) => track.stop())
        return
      }
      setCameraStream(stream)
    } catch (error) {
      if (requestId !== cameraRequestIdRef.current) return

      const name = error instanceof DOMException ? error.name : ''
      setCameraError(
        name === 'NotAllowedError'
          ? 'Autorisez l’accès à la caméra dans votre navigateur, puis réessayez.'
          : name === 'NotFoundError'
            ? 'Aucune caméra n’a été détectée. Vous pouvez choisir une photo depuis votre galerie.'
            : 'La caméra n’a pas pu démarrer. Vous pouvez choisir une photo depuis votre galerie.',
      )
    } finally {
      if (requestId === cameraRequestIdRef.current) setCameraLoading(false)
    }
  }, [])

  useEffect(() => {
    let mounted = true
    void Promise.resolve().then(() => {
      if (mounted) void startCamera()
    })

    return () => {
      mounted = false
      cameraRequestIdRef.current += 1
    }
  }, [startCamera])

  async function analyzeImage(image: string) {
    setPreview(image)
    setScanError(null)
    setResult(null)
    setUnclearResult(false)
    setScanning(true)

    try {
      const scanResult = await scan(image, guide)
      setResult(scanResult)
      const unclear = scanResult.confidence < 0.4 || scanResult.name.trim() === ''
      setUnclearResult(unclear)
      setPhase('result')
      if (unclear && 'speechSynthesis' in window) {
        window.speechSynthesis.cancel()
        window.speechSynthesis.speak(new SpeechSynthesisUtterance(
          'Je ne distingue pas bien l’article. Placez-le en entier dans le cadre, sur un fond uni, puis reprenez la photo.',
        ))
      }
    } catch (error) {
      setScanError(scanFailureMessage(error))
    } finally {
      setScanning(false)
    }
  }

  async function onFileSelected(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    if (file === undefined || scanning) return

    stopCamera()
    try {
      await analyzeImage(await resizeToBase64(file))
    } catch {
      setScanError('La photo n’a pas pu être préparée. Choisissez une autre image ou saisissez l’article manuellement.')
    } finally {
      event.target.value = ''
    }
  }

  function capturePhoto() {
    const video = videoRef.current
    if (video === null || video.videoWidth === 0 || video.videoHeight === 0) {
      setCameraError('L’aperçu caméra n’est pas prêt. Patientez un instant et réessayez.')
      return
    }

    const canvas = document.createElement('canvas')
    const scale = Math.min(1, 1024 / Math.max(video.videoWidth, video.videoHeight))
    canvas.width = Math.round(video.videoWidth * scale)
    canvas.height = Math.round(video.videoHeight * scale)
    const context = canvas.getContext('2d')
    if (context === null) {
      setCameraError('Impossible de capturer la photo sur cet appareil.')
      return
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    const image = canvas.toDataURL('image/jpeg', 0.8)
    stopCamera()
    void analyzeImage(image)
  }

  function useManualEntry() {
    setResult({
      name: '',
      type: guide,
      category: '',
      brand: '',
      color: '',
      size: '',
      material: '',
      condition: 'bon',
      confidence: 0,
    })
    setScanError(null)
    setPhase('form')
  }

  function reset() {
    stopCamera()
    setPhase('shoot')
    setPreview(null)
    setResult(null)
    setScanError(null)
    setCameraError(null)
    setSaveError(null)
    setUnclearResult(false)
    void startCamera()
  }

  async function onSave(values: {
    name: string
    brand: string
    color: string
    size: string
    material: string
    memory?: string
    notes?: string
    photos: string[]
    type: string
    category: string
    condition: string
    roomId: number | null
    storageLocationId: number | null
  }) {
    setSaveError(null)
    setSaving(true)

    try {
      await createItem({
        name: values.name,
        type: values.type,
        category: values.category === '' ? undefined : values.category,
        roomId: values.roomId,
        storageLocationId: values.storageLocationId,
        brand: values.brand === '' ? undefined : values.brand,
        color: values.color === '' ? undefined : values.color,
        size: values.size === '' ? undefined : values.size,
        material: values.material === '' ? undefined : values.material,
        condition: ITEM_CONDITIONS.includes(values.condition as (typeof ITEM_CONDITIONS)[number])
          ? (values.condition as (typeof ITEM_CONDITIONS)[number])
          : undefined,
        photos: values.photos.length > 0 ? values.photos : undefined,
      })
      navigate('/dressing')
    } catch (error) {
      setSaveError(error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.')
    } finally {
      setSaving(false)
    }
  }

  const initialValues =
    result === null
      ? undefined
      : {
          name: result.name,
          type: ITEM_TYPES.includes(result.type as (typeof ITEM_TYPES)[number])
            ? result.type
            : guide,
          category: result.category,
          brand: result.brand,
          color: result.color,
          size: result.size,
          material: result.material,
          condition: ITEM_CONDITIONS.includes(result.condition as (typeof ITEM_CONDITIONS)[number])
            ? result.condition
            : ('bon' as const),
          roomId: null,
          storageLocationId: null,
          photos: preview === null ? [] : [preview],
          memory: '',
          notes: '',
        }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <h1>Scanner un article</h1>

        {phase === 'shoot' && (
          <>
            <p className="auth-sub">
              Choisissez un type, prenez une photo, puis vérifiez les informations avant de l’enregistrer.
            </p>
            {cameraError === null && (
              <p className="auth-sub" role="status">
                La caméra démarre automatiquement. Placez un seul article en entier dans le cadre.
              </p>
            )}

            <div className="guide-scroll" role="radiogroup" aria-label="Type d’article">
              {GARMENT_TYPES.map((item) => (
                <button
                  key={item.value}
                  type="button"
                  role="radio"
                  aria-checked={guide === item.value}
                  className={guide === item.value ? 'guide-chip guide-chip--active' : 'guide-chip'}
                  onClick={() => setGuide(item.value)}
                >
                  {item.label}
                </button>
              ))}
            </div>

            {cameraStream !== null ? (
              <div className="scan-camera">
                <div className={`scan-camera-preview scan-camera-preview--${guide}`}>
                  <video ref={videoRef} autoPlay muted playsInline aria-label="Aperçu de la caméra" />
                  <ScanFrameOverlay type={guide} />
                </div>
                <div className="scan-camera-actions">
                  <button type="button" className="btn btn-primary" onClick={capturePhoto}>
                    <Camera size={18} aria-hidden="true" /> Prendre la photo
                  </button>
                  <button
                    type="button"
                    className="btn btn-secondary"
                    disabled={scanning}
                    onClick={() => galleryInputRef.current?.click()}
                  >
                    <ImagePlus size={18} aria-hidden="true" /> Choisir depuis la galerie
                  </button>
                  <button type="button" className="btn btn-secondary" onClick={stopCamera}>
                    <X size={18} aria-hidden="true" /> Annuler
                  </button>
                </div>
              </div>
            ) : (
              <>
                <div className="account-actions">
                  <button
                    type="button"
                    className="btn btn-primary"
                    disabled={scanning}
                    onClick={() => galleryInputRef.current?.click()}
                  >
                    <ImagePlus size={18} aria-hidden="true" /> Choisir depuis la galerie
                  </button>
                  {cameraError !== null && (
                    <button type="button" className="btn btn-secondary" disabled={cameraLoading} onClick={() => void startCamera()}>
                      <Camera size={18} aria-hidden="true" /> Réessayer la caméra
                    </button>
                  )}
                </div>
              </>
            )}

            <input
              ref={galleryInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              aria-label="Choisir depuis la galerie"
              onChange={(event) => void onFileSelected(event)}
            />

            {cameraLoading && <p role="status" className="auth-sub">Activation de la caméra…</p>}
            {cameraError !== null && <p role="alert" className="form-error">{cameraError}</p>}
            {scanning && <p role="status" className="auth-sub">Analyse de la photo…</p>}
            {scanError !== null && (
              <div className="scan-fallback">
                <p role="alert" className="form-error">{scanError}</p>
                {preview !== null && (
                  <img src={preview} alt="Photo conservée pour la saisie manuelle" className="scan-preview" />
                )}
                <div className="account-actions">
                  {preview !== null && (
                    <button type="button" className="btn btn-primary" onClick={() => void analyzeImage(preview)} disabled={scanning}>
                      <RotateCcw size={18} aria-hidden="true" /> Réessayer l’analyse
                    </button>
                  )}
                  <button type="button" className="btn btn-secondary" onClick={useManualEntry}>
                    Saisir l’article manuellement
                  </button>
                </div>
              </div>
            )}
          </>
        )}

        {phase === 'result' && preview !== null && result !== null && (
          <>
            <img src={preview} alt="Article photographié" className="scan-preview" />
            {unclearResult && (
              <div className="scan-quality-warning" role="status">
                <p>L’article n’est pas assez net ou n’est pas entièrement dans le cadre. Vérifiez son placement avant de continuer.</p>
                <button type="button" className="btn btn-secondary" onClick={reset}>
                  Reprendre la photo
                </button>
              </div>
            )}
            <ul className="scan-result">
              <li><span>Nom</span><strong>{result.name === '' ? '—' : result.name}</strong></li>
              <li><span>Type</span><strong>{result.type}</strong></li>
              <li><span>Couleur</span><strong>{result.color === '' ? '—' : result.color}</strong></li>
              <li><span>Marque</span><strong>{result.brand === '' ? '—' : result.brand}</strong></li>
              <li><span>Confiance</span><strong>{Math.round(result.confidence * 100)} %</strong></li>
            </ul>
            <div className="account-actions">
              <button type="button" className="btn btn-primary" onClick={() => setPhase('form')}>
                Vérifier et compléter
              </button>
              <button type="button" className="btn btn-secondary" onClick={reset}>
                Scanner une autre photo
              </button>
            </div>
          </>
        )}

        {phase === 'form' && initialValues !== undefined && (
          <>
            {saveError !== null && <p role="alert" className="form-error">{saveError}</p>}
            {saving ? (
              <p className="auth-sub">Enregistrement…</p>
            ) : (
              <ItemForm
                initialValues={initialValues}
                onSubmit={(values) => void onSave(values)}
                onClose={() => setPhase(result?.confidence === 0 ? 'shoot' : 'result')}
              />
            )}
          </>
        )}
      </div>
    </div>
  )
}
