.PHONY: test lint typecheck build
test:
	composer test
lint:
	composer lint
typecheck:
	composer analyse
build:
	composer validate --strict
