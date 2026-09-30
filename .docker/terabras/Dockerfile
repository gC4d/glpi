# Terabras — application image.
#
# Layered on the same base as the GLPI development image so the PHP extension set
# is identical, but with the product's own PHP limits and an entrypoint that makes
# every boot converge on a provisioned instance.
#
# Persistence contract: GLPI's configuration (config_db.php, glpicrypt.key, the
# OAuth key pair) and its variable data (documents, cache, sessions, logs) are
# moved OUT of the application tree via GLPI_CONFIG_DIR / GLPI_VAR_DIR, so that
# rebuilding or replacing the application image can never take an installed
# instance back to "GLPI must be configured".
ARG PHP_VERSION=8.4

FROM ghcr.io/glpi-project/glpi-development-env:${PHP_VERSION}

USER root

ENV PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d/:/usr/local/etc/php/custom_conf.d/"

# Keep www-data aligned with the host user so a bind-mounted source tree stays
# readable/writable. Irrelevant once the source is COPYed into the image.
ARG HOST_GROUP_ID=1000
ARG HOST_USER_ID=1000
RUN groupmod --gid ${HOST_GROUP_ID} www-data \
 && usermod --uid ${HOST_USER_ID} www-data

# The entrypoint renders the PHP limits from environment variables at boot, so it
# must be able to write here as www-data.
RUN mkdir -p /usr/local/etc/php/custom_conf.d/ \
 && chown www-data:www-data /usr/local/etc/php/custom_conf.d/

# Configuration and variable data live outside the app tree, on volumes.
ENV GLPI_CONFIG_DIR=/var/glpi/config \
    GLPI_VAR_DIR=/var/glpi/files
RUN mkdir -p /var/glpi/config /var/glpi/files \
 && chown -R www-data:www-data /var/glpi

# Terabras is a product, not a development checkout.
ENV GLPI_ENVIRONMENT_TYPE=production

# NOTE for an immutable image: once the source is COPYed in rather than
# bind-mounted, add
#     RUN php bin/console build:compile_scss
# here. GLPI keys its runtime SCSS cache on the GLPI VERSION, not on stylesheet
# contents, and that cache lives on the data volume — so without precompiled
# files in public/css_compiled/ a restyle never reaches the browser. The
# entrypoint does this at boot (guarded by a fingerprint) for the bind-mounted
# setup.

# Effective upload ceiling of the stack. `document_max_size` in GLPI is realigned
# to min(upload_max_filesize, post_max_size) by the provisioning step, so these two
# values are the single source of truth for "how big a document can be".
ENV PHP_UPLOAD_MAX_FILESIZE=128M \
    PHP_POST_MAX_SIZE=144M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_MAX_INPUT_TIME=300 \
    PHP_MAX_FILE_UPLOADS=50

# The base image ships a development vhost that pins
# `SetEnv GLPI_ENVIRONMENT_TYPE development` (plus testing/e2e vhosts). A vhost-level
# SetEnv beats a server-level one, so the product must replace the site rather than
# add to it. The entrypoint renders the replacement, because the values come from the
# container environment.
RUN rm -f /etc/apache2/sites-enabled/*.conf \
 && chown www-data:www-data /etc/apache2/sites-enabled

COPY entrypoint.sh /usr/local/bin/terabras-entrypoint
RUN chmod +x /usr/local/bin/terabras-entrypoint

USER www-data

ENTRYPOINT ["terabras-entrypoint"]
CMD ["apache2-foreground"]
