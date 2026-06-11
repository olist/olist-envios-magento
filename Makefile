.PHONY: install test test-verbose clean \
        dev-up dev-down dev-stop dev-shell dev-logs dev-upgrade

install:
	composer install

test:
	vendor/bin/phpunit

test-verbose:
	vendor/bin/phpunit --testdox

clean:
	rm -rf vendor .phpunit.result.cache composer.lock

COMPOSE = docker compose -f development/docker-compose.yml

dev-up:
	$(COMPOSE) up -d

dev-stop:
	$(COMPOSE) stop

dev-down:
	$(COMPOSE) down

dev-shell:
	$(COMPOSE) exec magento bash

dev-logs:
	$(COMPOSE) logs -f init

dev-upgrade:
	$(COMPOSE) exec magento php bin/magento setup:upgrade
	$(COMPOSE) exec magento php bin/magento cache:flush
