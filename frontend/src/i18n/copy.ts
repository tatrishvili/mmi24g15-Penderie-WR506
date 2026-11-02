export interface AppCopy {
  childSignInNotice: string
  unexpectedError: string
  nav: {
    friends: string
  }
  home: {
    titleLines: string[]
    tagline: string
    next: string
    signIn: string
    stepLabel: string
  }
  onboard: {
    next: string
    signIn: string
    organise: {
      titleLine: string
      introLines: string[]
      tabs: string[]
      rooms: string[]
      stepLabel: string
    }
    lend: {
      titleLines: string[]
      itemName: string
      status: string
      since: string
      borrower: string
      chips: string[]
      bodyLines: string[]
      timeline: string[]
      stepLabel: string
    }
    look: {
      titleLines: string[]
      bodyLines: string[]
      temperature: string
      today: string
      like: string
      skip: string
      caption: string
      stepLabel: string
    }
  }
  register: {
    titleLines: string[]
    intro: string
    usernameLabel: string
    emailLabel: string
    birthDateLabel: string
    passwordLabel: string
    usernamePlaceholder: string
    emailPlaceholder: string
    birthDatePlaceholder: string
    passwordPlaceholder: string
    birthDateHint: string
    termsLead: string
    termsLink: string
    submit: string
    switchPrompt: string
    switchLink: string
  }
  login: {
    title: string
    subtitle: string
    emailLabel: string
    passwordLabel: string
    emailPlaceholder: string
    passwordPlaceholder: string
    rememberMe: string
    forgotPassword: string
    submit: string
    switchPrompt: string
    switchLink: string
  }
  verify: {
    setupLater: string
    titleLines: string[]
    sentLead: string
    sentInstruction: string
    tokenNotice: string
    noTokenLead: string
    expiredError: string
    genericError: string
    emailLabel: string
    confirmButton: string
    confirmingButton: string
    resendButton: string
    resendingButton: string
    resendSuccess: string
    resendFailure: string
    switchPrompt: string
    switchLink: string
    backToLogin: string
  }
  profiles: {
    pinLabel: string
    pinNote: string
  }
  errors: {
    notFound: string
    conflict: string
    forbidden: string
    invalid: string
    rateLimited: string
  }
  friends: {
    title: string
    tagline: string
    addTitle: string
    addDescription: string
    usernameLabel: string
    usernamePlaceholder: string
    send: string
    follow: string
    followed: string
    following: string
    followers: string
    noFollowers: string
    noFollowing: string
    unfollow: string
    sending: string
    sent: string
    incoming: string
    noIncoming: string
    outgoing: string
    noOutgoing: string
    mine: string
    noFriends: string
    accept: string
    decline: string
    cancel: string
    remove: string
    adultOnly: string
    loading: string
  }
  account: {
    usernameTitle: string
    usernameDescription: string
    usernameLabel: string
    usernamePlaceholder: string
    usernameSave: string
    usernameSaving: string
    usernameSaved: string
    usernameTaken: string
    usernameError: string
  }
  rooms: {
    addRoom: string
    chooseRoom: string
    customOption: string
    customNameLabel: string
    customNamePlaceholder: string
    presets: string[]
  }
}

const copies: Record<'fr' | 'en', AppCopy> = {
  fr: {
    childSignInNotice: 'Les enfants ne peuvent pas encore se connecter depuis un autre appareil. Un parent peut ouvrir leur profil sur un appareil déjà connecté.',
    unexpectedError: 'Une erreur inattendue est survenue.',
    nav: { friends: 'Amis' },
    home: {
      titleLines: ['Bienvenue', 'chez Penderie'],
      tagline:
        'Ajoutez tous vos vêtements, accessoires et objets. Scannez, organisez, et retrouvez tout en un coup d’œil.',
      next: 'Suivant',
      signIn: 'Se connecter',
      stepLabel: 'Étape 1 sur 4',
    },
    onboard: {
      next: 'Suivant',
      signIn: 'Se connecter',
      organise: {
        titleLine: 'Tout à sa place',
        introLines: [
          'Retrouvez facilement chaque vêtement',
          'et chaque objet grâce à un rangement organisé',
          'par pièce, personne et emplacement.',
        ],
        tabs: ['Pièces', 'Personnes', 'Emplacement'],
        rooms: ['Dressing', 'Chambre', 'Bureau', 'Salon', 'Garage', 'Cuisine'],
        stepLabel: 'Étape 2 sur 4',
      },
      lend: {
        titleLines: ['Prêtez vos affaires', 'sans jamais oublier'],
        itemName: 'veste beige',
        status: 'Prêtée',
        since: 'Depuis le 12 septembre',
        borrower: 'Sophie',
        chips: ['Taille S', 'état parfait'],
        bodyLines: [
          'Je sais toujours qui possède mes affaires.',
          'Recevez les demandes de vos proches et gardez',
          'toujours une trace de vos prêts.',
        ],
        timeline: ['Demande reçue', 'Accepté', 'En prêt'],
        stepLabel: 'Étape 3 sur 4',
      },
      look: {
        titleLines: ['Des looks', 'chaque jour'],
        bodyLines: [
          'Penderie vous aide à créer des tenues adaptées',
          'à votre style, à la météo et à vos envies.',
        ],
        temperature: '18°',
        today: 'Aujourd’hui',
        like: 'J’aime',
        skip: 'Pas pour moi',
        caption: 'Choisissez le style qui vous correspond le mieux',
        stepLabel: 'Étape 4 sur 4',
      },
    },
    register: {
      titleLines: ['Créez votre', 'compte'],
      intro: 'Créez votre espace Penderie et ajoutez les membres de votre famille en quelques clics',
      usernameLabel: 'Nom d’utilisateur',
      emailLabel: 'E-mail',
      birthDateLabel: 'Date de naissance',
      passwordLabel: 'Mot de passe (8 caractères minimum)',
      usernamePlaceholder: 'Nom d’utilisateur',
      emailPlaceholder: 'Mail',
      birthDatePlaceholder: 'Date de naissance',
      passwordPlaceholder: 'Mot de passe',
      birthDateHint:
        'Cette date est déclarative, pas une vérification officielle. L’inscription adulte est réservée aux personnes de 18 ans ou plus ; les mineurs doivent utiliser un profil créé par un parent.',
      termsLead: 'En créant le compte vous acceptez les',
      termsLink: 'conditions utilisateurs',
      submit: "S'inscrire",
      switchPrompt: 'vous avez déjà un compte ?',
      switchLink: 'Se connecter',
    },
    login: {
      title: 'Se connecter',
      subtitle: 'Ravi de vous revoir !',
      emailLabel: 'E-mail',
      passwordLabel: 'Mot de passe',
      emailPlaceholder: 'E-mail',
      passwordPlaceholder: 'Mot de passe',
      rememberMe: 'Se souvenir de moi',
      forgotPassword: 'Mot de passe oublié ?',
      submit: 'Se connecter',
      switchPrompt: 'vous n’avez pas de compte ?',
      switchLink: 'S’inscrire',
    },
    verify: {
      setupLater: 'Je le ferai plus tard',
      titleLines: ['Vérifiez votre', 'e-mail'],
      sentLead: 'Un e-mail de confirmation a été envoyé à :',
      sentInstruction: 'Cliquez sur le lien reçu pour activer votre compte.',
      tokenNotice:
        'Confirmez cette adresse pour créer votre compte. Votre mot de passe n’est jamais envoyé par e-mail.',
      noTokenLead:
        'Le lien ne contient pas de jeton de confirmation. Saisissez votre adresse pour demander un nouveau lien.',
      expiredError: 'Ce lien est invalide ou a expiré. Vous pouvez demander un nouveau lien ci-dessous.',
      genericError: 'Impossible de confirmer votre adresse pour le moment. Réessayez.',
      emailLabel: 'E-mail',
      confirmButton: 'Confirmer et créer mon compte',
      confirmingButton: 'Confirmation en cours…',
      resendButton: 'Renvoyer le lien',
      resendingButton: 'Envoi en cours…',
      resendSuccess:
        'Si une inscription en attente existe pour cette adresse, un nouveau lien sera envoyé. Attendez au moins une minute entre deux demandes.',
      resendFailure: 'Impossible de traiter la demande pour le moment. Réessayez plus tard.',
      switchPrompt: 'vous avez déjà un compte ?',
      switchLink: 'Se connecter',
      backToLogin: 'Retour à la connexion',
    },
    profiles: {
      pinLabel: 'Code PIN pour cet appareil (optionnel)',
      pinNote: 'Ce code protège le changement de profil sur cet appareil. Il ne permet pas une connexion sur un autre appareil.',
    },
    errors: {
      notFound: 'Aucun compte vérifié trouvé avec ce nom d’utilisateur. Vérifiez son orthographe et demandez à votre ami de confirmer son adresse e-mail.',
      conflict: 'Cette demande ou relation existe déjà, ou n’est plus disponible.',
      forbidden: 'Cette action est réservée aux comptes adultes vérifiés.',
      invalid: 'Vérifiez le nom d’utilisateur saisi.',
      rateLimited: 'Trop d’actions en peu de temps. Attendez un moment avant de réessayer.',
    },
    friends: {
      title: 'Amis',
      tagline: 'Votre cercle de confiance',
      addTitle: 'Ajouter un ami',
      addDescription: 'Saisissez son nom d’utilisateur (visible dans « Mon compte », pas son e-mail). Vous pouvez le suivre directement ou lui envoyer une demande d’amitié : il sera suivi tout de suite, et vous deviendrez amis s’il accepte. Son adresse e-mail doit être confirmée.',
      usernameLabel: 'Nom d’utilisateur',
      usernamePlaceholder: 'Ex. camille_92',
      send: 'Envoyer la demande',
      follow: 'Suivre',
      followed: 'Vous suivez maintenant ce compte.',
      following: 'Abonnements',
      followers: 'Abonnés',
      noFollowers: 'Aucun abonné pour le moment.',
      noFollowing: 'Vous ne suivez personne pour le moment.',
      unfollow: 'Ne plus suivre',
      sending: 'Envoi…',
      sent: 'Demande envoyée.',
      incoming: 'Demandes reçues',
      noIncoming: 'Aucune demande reçue pour le moment.',
      outgoing: 'Demandes envoyées',
      noOutgoing: 'Aucune demande en attente.',
      mine: 'Mes amis',
      noFriends: 'Vos amis apparaîtront ici après acceptation.',
      accept: 'Accepter la demande',
      decline: 'Refuser la demande',
      cancel: 'Annuler la demande',
      remove: 'Retirer cet ami',
      adultOnly: 'Les demandes d’amitié sont réservées aux comptes adultes.',
      loading: 'Chargement…',
    },
    account: {
      usernameTitle: 'Nom d’utilisateur',
      usernameDescription: 'Votre ancien compte n’a pas encore de nom d’utilisateur. Choisissez-en un pour que vos amis puissent vous trouver. Vous ne pourrez plus le modifier.',
      usernameLabel: 'Nom d’utilisateur',
      usernamePlaceholder: 'Ex. penderie_test',
      usernameSave: 'Enregistrer',
      usernameSaving: 'Enregistrement…',
      usernameSaved: 'Nom d’utilisateur enregistré.',
      usernameTaken: 'Ce nom d’utilisateur est déjà utilisé. Choisissez-en un autre.',
      usernameError: 'Impossible d’enregistrer le nom d’utilisateur. Réessayez.',
    },
    rooms: {
      addRoom: 'Ajouter une pièce',
      chooseRoom: 'Choisir une pièce',
      customOption: 'Autre (nom personnalisé)',
      customNameLabel: 'Nom personnalisé de la pièce',
      customNamePlaceholder: 'Ex. Atelier couture',
      presets: ['Dressing', 'Salon', 'Chambre', 'Cuisine', 'Salle de bain', 'Entrée', 'Bureau', 'Garage', 'Cave', 'Grenier', 'Buanderie'],
    },
  },
  en: {
    childSignInNotice: 'Children cannot sign in from another device yet. A parent can open their profile on an already signed-in device.',
    unexpectedError: 'Something unexpected happened.',
    nav: { friends: 'Friends' },
    home: {
      titleLines: ['Welcome to', 'Penderie'],
      tagline:
        'Add all your clothes, accessories and objects. Scan, organize, and find everything at a glance.',
      next: 'Next',
      signIn: 'Sign in',
      stepLabel: 'Step 1 of 4',
    },
    onboard: {
      next: 'Next',
      signIn: 'Sign in',
      organise: {
        titleLine: 'Everything in its place',
        introLines: [
          'Find every garment and every object easily',
          'thanks to storage organized by room,',
          'person and place',
        ],
        tabs: ['Rooms', 'People', 'Places'],
        rooms: ['Closet', 'Bedroom', 'Office', 'Living room', 'Garage', 'Kitchen'],
        stepLabel: 'Step 2 of 4',
      },
      lend: {
        titleLines: ['Lend your things', 'without ever forgetting'],
        itemName: 'beige jacket',
        status: 'Lent',
        since: 'Since 12 September',
        borrower: 'Sophie',
        chips: ['Size S', 'perfect condition'],
        bodyLines: [
          'I always know who owns my things.',
          'Receive requests from your loved ones and',
          'always keep a record of your loans.',
        ],
        timeline: ['Request received', 'Accepted', 'On loan'],
        stepLabel: 'Step 3 of 4',
      },
      look: {
        titleLines: ['New looks', 'every day'],
        bodyLines: [
          'Penderie helps you create outfits that match',
          'your style, the weather and your mood.',
        ],
        temperature: '18°',
        today: 'Today',
        like: 'I like it',
        skip: 'Not for me',
        caption: 'Pick the style that suits you best',
        stepLabel: 'Step 4 of 4',
      },
    },
    register: {
      titleLines: ['Create your', 'account'],
      intro: 'Create your Penderie space and add your family members in a few clicks',
      usernameLabel: 'Username',
      emailLabel: 'E-mail',
      birthDateLabel: 'Date of birth',
      passwordLabel: 'Password (8 characters minimum)',
      usernamePlaceholder: 'Username',
      emailPlaceholder: 'Mail',
      birthDatePlaceholder: 'Date of birth',
      passwordPlaceholder: 'Password',
      birthDateHint:
        'This date is self-declared, not an official verification. Adult registration is for people aged 18 or over; minors must use a profile created by a parent.',
      termsLead: 'By creating the account you accept the',
      termsLink: 'terms of use',
      submit: 'Sign up',
      switchPrompt: 'already have an account?',
      switchLink: 'Sign in',
    },
    login: {
      title: 'Sign in',
      subtitle: 'Welcome back!',
      emailLabel: 'E-mail',
      passwordLabel: 'Password',
      emailPlaceholder: 'E-mail',
      passwordPlaceholder: 'Password',
      rememberMe: 'Remember me',
      forgotPassword: 'Forgot password?',
      submit: 'Sign in',
      switchPrompt: 'no account yet?',
      switchLink: 'Sign up',
    },
    verify: {
      setupLater: 'I’ll set this up later',
      titleLines: ['Check your', 'e-mail'],
      sentLead: 'A confirmation e-mail has been sent to:',
      sentInstruction: 'Click the link we sent you to activate your account.',
      tokenNotice:
        'Confirm this address to create your account. Your password is never sent by e-mail.',
      noTokenLead:
        'This link contains no confirmation token. Enter your address to request a new link.',
      expiredError: 'This link is invalid or has expired. You can request a new one below.',
      genericError: 'We could not confirm your address right now. Try again.',
      emailLabel: 'E-mail',
      confirmButton: 'Confirm and create my account',
      confirmingButton: 'Confirming…',
      resendButton: 'Resend the link',
      resendingButton: 'Sending…',
      resendSuccess:
        'If a pending registration exists for this address, a new link will be sent. Wait at least one minute between requests.',
      resendFailure: 'We could not process your request right now. Try again later.',
      switchPrompt: 'already have an account?',
      switchLink: 'Sign in',
      backToLogin: 'Back to sign in',
    },
    profiles: {
      pinLabel: 'PIN for this device (optional)',
      pinNote: 'This PIN protects profile switching on this device. It cannot be used to sign in on another device.',
    },
    errors: {
      notFound: 'No verified account was found with that username. Check the spelling and ask your friend to verify their email address.',
      conflict: 'This request or relationship already exists, or is no longer available.',
      forbidden: 'This action is for verified adult accounts only.',
      invalid: 'Check the username you entered.',
      rateLimited: 'Too many actions in a short time. Wait a little before trying again.',
    },
    friends: {
      title: 'Friends',
      tagline: 'Your circle of trust',
      addTitle: 'Add a friend',
      addDescription: 'Enter their username (shown in My account, not their email). Follow them directly or send a friend request: the request follows them immediately, and you become friends if they accept. Their email must be verified.',
      usernameLabel: 'Username',
      usernamePlaceholder: 'e.g. camille_92',
      send: 'Send request',
      follow: 'Follow',
      followed: 'You are now following this account.',
      following: 'Following',
      followers: 'Followers',
      noFollowers: 'No followers yet.',
      noFollowing: 'You are not following anyone yet.',
      unfollow: 'Unfollow',
      sending: 'Sending…',
      sent: 'Request sent.',
      incoming: 'Received requests',
      noIncoming: 'No requests received yet.',
      outgoing: 'Sent requests',
      noOutgoing: 'No pending requests.',
      mine: 'My friends',
      noFriends: 'Friends will appear here after they accept.',
      accept: 'Accept request',
      decline: 'Decline request',
      cancel: 'Cancel request',
      remove: 'Remove friend',
      adultOnly: 'Friend requests are for adult accounts only.',
      loading: 'Loading…',
    },
    account: {
      usernameTitle: 'Username',
      usernameDescription: 'Your existing account does not have a username yet. Choose one so friends can find you. You will not be able to change it later.',
      usernameLabel: 'Username',
      usernamePlaceholder: 'e.g. penderie_test',
      usernameSave: 'Save username',
      usernameSaving: 'Saving…',
      usernameSaved: 'Username saved.',
      usernameTaken: 'That username is already in use. Choose another one.',
      usernameError: 'Could not save the username. Please try again.',
    },
    rooms: {
      addRoom: 'Add a room',
      chooseRoom: 'Choose a room',
      customOption: 'Other (custom name)',
      customNameLabel: 'Custom room name',
      customNamePlaceholder: 'e.g. Sewing room',
      presets: ['Dressing room', 'Living room', 'Bedroom', 'Kitchen', 'Bathroom', 'Entryway', 'Office', 'Garage', 'Cellar', 'Attic', 'Laundry room'],
    },
  },
}

export function getCopy(): AppCopy {
  return document.documentElement.lang.toLowerCase().startsWith('en') ? copies.en : copies.fr
}
