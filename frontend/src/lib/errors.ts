export const errorText = (error: unknown) =>
  error instanceof Error ? error.message : 'Une erreur est survenue. Réessayez.'
