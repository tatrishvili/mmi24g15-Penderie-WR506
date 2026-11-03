FROM nginx:alpine

# Keep the gateway independent of Docker Desktop's WSL file bind mounts.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
