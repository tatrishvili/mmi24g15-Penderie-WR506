// Lighthouse CI — performance, accessibility, best practices and SEO budgets.
// Run locally with `make lighthouse` (builds first), or let CI run it on every push.
module.exports = {
  ci: {
    collect: {
      // The production build is served by LHCI itself, no extra server needed.
      staticDistDir: './dist',
      url: ['/'],
      numberOfRuns: 1,
      settings: {
        preset: 'desktop',
        // Chromium runs as an unprivileged user inside the container.
        chromeFlags: '--no-sandbox --disable-dev-shm-usage --disable-gpu',
        // The API is not running during a static audit; the landing page
        // degrades gracefully to its offline badge.
        blockedUrlPatterns: ['*/api/*'],
      },
    },
    assert: {
      assertions: {
        'categories:accessibility': ['error', { minScore: 0.9 }],
        'categories:best-practices': ['error', { minScore: 0.9 }],
        'categories:seo': ['warn', { minScore: 0.9 }],
        'categories:performance': ['warn', { minScore: 0.7 }],
      },
    },
    upload: {
      target: 'filesystem',
      outputDir: './lighthouse-report',
    },
  },
}
