.PHONY: install test test-verbose clean

install:
	composer install

test:
	vendor/bin/phpunit

test-verbose:
	vendor/bin/phpunit --testdox

clean:
	rm -rf vendor .phpunit.result.cache composer.lock
