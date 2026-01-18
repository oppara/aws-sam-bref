
build-TryFormFunction:
	composer install --no-dev --optimize-autoloader
	rsync -ahv --exclude-from=exclude ./ $(ARTIFACTS_DIR)
