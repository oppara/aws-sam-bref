SHELL := /bin/bash

PHP_IMAGE := php:8.4-cli
PHP_PORT  := 8088
DOCROOT   := public
APP_DIR   := /src
CONTAINER_NAME := slim-form-php
ENV_FILE := .env

all: help

.PHONY: build
build: 
	@sam build

.PHONY: prod
prod: ## deploy production stack
	$(MAKE) build
	$(MAKE) deploy-prod

.PHONY: deploy-prod
deploy-prod:
	@sam deploy --config-env prod

.PHONY: destroy-prod
destroy-prod: ## delete producion stack
	@sam delete --config-env prod

.PHONY: up
up: ## start PHP built-in server via Docker
	@docker run --rm \
		--env-file "$$(pwd)/src/$(ENV_FILE)" \
		--name $(CONTAINER_NAME) \
		-p $(PHP_PORT):$(PHP_PORT) \
		-v "$$(pwd)/src":$(APP_DIR) \
		-w $(APP_DIR) \
		$(PHP_IMAGE) \
		php -S 0.0.0.0:$(PHP_PORT) -t $(DOCROOT)

.PHONY: composer-install
composer-install: ## composer install via Docker
	@docker run --rm \
		-v "$$(pwd)/src":$(APP_DIR) \
		-w $(APP_DIR) \
		composer:2 \
		composer install

.PHONY: open
open: ## opne in broswer
	open http://localhost:${PHP_PORT}/contact

.PHONY: help
help: ## Display this help screen
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
