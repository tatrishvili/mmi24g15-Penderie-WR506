import { itemText as t } from './itemCatalog.ts'

const french: Record<string, string> = {
  'Child access is disabled.': 'L’accès enfant est suspendu.',
  'Choose at least one person.': 'Choisissez au moins une personne.',
  'Invalid username or password.': 'Nom d’utilisateur ou mot de passe incorrect.',
  'Please wait a minute before trying again.': 'Patientez une minute avant de réessayer.',
  'Choose your own password before continuing.':
    'Choisis ton propre mot de passe avant de continuer.',
  'Only the responsible mother or father can manage this child.':
    'Seuls la mère et le père responsables peuvent gérer cet enfant.',
  'Child not found.': 'Profil enfant introuvable.',
  'Enter a valid value.': 'Saisissez une valeur valide.',
  'Choose mother or father.': 'Choisissez mère ou père.',
  'Choose a password of 15 to 128 characters; avoid common passwords.':
    'Choisissez un mot de passe de 15 à 128 caractères et évitez les mots de passe courants.',
  'Use 3 to 30 letters, numbers or underscores.':
    'Utilisez 3 à 30 lettres, chiffres ou tirets bas.',
  'Choose another child username.':
    'Ce nom d’utilisateur enfant est déjà utilisé. Choisissez-en un autre.',
  'Child accounts are for ages 12 to 17.': 'Les comptes enfants sont réservés aux 12 à 17 ans.',
  'Choose a valid child.': 'Choisissez un profil enfant valide.',
  'This child already has an account.': 'Cet enfant possède déjà un compte.',
  'Choose another verified parent account.': 'Choisissez le compte vérifié de l’autre parent.',
  'This parent already has an invitation or access.':
    'Ce parent a déjà une invitation ou un accès.',
  'Only the two responsible parents can manage this account.':
    'Seuls les deux parents responsables peuvent gérer ce compte.',
  'This parent invitation is unavailable.':
    'Cette invitation parentale est expirée ou indisponible.',
  'Choose a valid list.': 'Choisissez une liste valide.',
  'Choose valid identifiers.': 'Choisissez des éléments valides.',
  'Choose an item audience.': 'Choisissez qui pourra voir cet objet.',
  'Personal or archived items cannot be shared.':
    'Les objets personnels ou archivés ne peuvent pas être partagés.',
  'Personal or archived content cannot be shared.':
    'Les contenus personnels ou archivés ne peuvent pas être partagés.',
  'Choose people from this household.': 'Choisissez des personnes de ce foyer.',
  'Item not found.': 'Objet introuvable ou inaccessible.',
  'This item changed. Reload it before sharing.':
    'Cet objet a changé. Rechargez-le avant de le partager.',
  'Ask your parent to activate your child account first.':
    'Demande d’abord à ton parent d’activer ton compte enfant.',
  'Review the displayed item and selected recipients before sharing.':
    'Vérifiez l’aperçu et les destinataires avant de partager.',
  'Reload this collection before saving.': 'Rechargez cette collection avant de l’enregistrer.',
  "Collections can contain only this child's active items.":
    'La collection doit contenir uniquement les objets actifs de cet enfant.',
  'This contact cannot receive a request.': 'Ce contact ne peut pas recevoir de demande.',
  'Connection not found.': 'Contact introuvable.',
  'This request is no longer pending.': 'Cette demande a déjà été traitée.',
  'Only the invited contact can accept.': 'Seul le contact invité peut accepter.',
  'Only the responsible mother or father can approve this connection.':
    'Seuls la mère et le père responsables peuvent approuver ce contact.',
  'Choose accept, approve, reject or revoke.': 'Choisissez une action valide.',
  'Collection not found.': 'Collection introuvable.',
  'Collection contents changed.': 'Le contenu de la collection a changé.',
  'Choose an item or collection.': 'Choisissez un objet ou une collection.',
  'Choose a source.': 'Choisissez un contenu à partager.',
  'Reload this content before requesting approval.':
    'Rechargez ce contenu avant de demander un accord.',
  'Choose approved contacts.': 'Choisissez des contacts déjà approuvés.',
  'This content changed or child access was disabled. Ask for a new approval.':
    'Le contenu a changé ou l’accès enfant a été suspendu. Une nouvelle demande est nécessaire.',
  'This request expired or was already handled.':
    'Cette demande est expirée ou a déjà été traitée.',
  'This item is unavailable.': 'Cet objet n’est plus disponible.',
  'This item changed. Ask for a new approval.':
    'Cet objet a changé. Une nouvelle demande est nécessaire.',
  'This content changed. Ask for a new approval.':
    'Ce contenu a changé. Une nouvelle demande est nécessaire.',
  'This contact is no longer approved.': 'Ce contact n’est plus approuvé.',
  'Send a valid JSON object.': 'Les informations envoyées sont invalides.',
  'A child session is required.': 'Connectez-vous avec un compte enfant.',
  'The current password is incorrect.': 'Le mot de passe actuel est incorrect.',
  'Choose a different password.': 'Choisis un mot de passe différent du mot de passe initial.',
  'Activate this child account first.': 'Activez d’abord ce compte enfant.',
  'Child account not found.': 'Compte enfant introuvable.',
  'Choose enabled or disabled.': 'Choisissez d’activer ou de suspendre l’accès.',
  'Invitation not found.': 'Invitation introuvable.',
  'Parent link not found.': 'Accès parental introuvable.',
  'Only the inviting parent can revoke this additional parent.':
    'Seul le parent à l’origine de l’invitation peut retirer cet accès supplémentaire.',
  'Approval not found.': 'Demande d’accord introuvable.',
  'Send a decision and the reviewed version.': 'Choisissez une décision pour l’aperçu consulté.',
  'This request was already handled. Reload your notifications.':
    'Cette demande a déjà été traitée. Rechargez vos notifications.',
  'Notification not found.': 'Notification introuvable.',
  'Publication not found.': 'Partage introuvable.',
  'Photo not found.': 'Photo introuvable.',
  'Reviewed photo is no longer available.': 'La photo de cet aperçu n’est plus disponible.',
  'Each adult must sign in with their own account.':
    'Chaque adulte doit se connecter avec son propre compte.',
  'Only the responsible mother or father can unlock this profile.':
    'Seuls la mère et le père responsables peuvent ouvrir ce profil.',
  'Room not found.': 'Pièce introuvable ou non autorisée.',
  'Storage location not found.': 'Rangement introuvable ou non autorisé.',
  'Choose a valid room type.': 'Choisissez un type de pièce valide.',
  'Choose valid household profiles.': 'Choisissez des membres de ce foyer.',
  'Only the owner or responsible parent can add this item.':
    'Seuls le propriétaire ou ses parents responsables peuvent ajouter cet objet.',
}
export const errorText = (error: unknown) => {
  if (!(error instanceof Error))
    return t('Une erreur est survenue. Réessayez.', 'Something went wrong. Please try again.')
  return t(french[error.message] ?? error.message, error.message)
}
