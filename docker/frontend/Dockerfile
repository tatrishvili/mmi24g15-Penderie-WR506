# ----------------------------------------------------------------------------
#  Frontend runtime — Node 24 + Chromium
#
#  Chromium is only there so the Lighthouse audits (`make lighthouse`, and the
#  Lighthouse CI job) can open the production build locally. The Vite dev
#  server does not need it.
# ----------------------------------------------------------------------------

FROM node:24-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium \
        fonts-liberation \
        fonts-noto-color-emoji \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# chrome-launcher (used by Lighthouse) looks for Chrome through CHROME_PATH.
ENV CHROME_PATH=/usr/bin/chromium \
    PUPPETEER_SKIP_DOWNLOAD=true

WORKDIR /app
