
.PHONY: install
install: install_dependencies test docs

.PHONY: install_dependencies
install_dependencies:
	composer install

.PHONY: test
test:
	phpunit --bootstrap vendor/autoload.php test

.PHONY: docs
docs:
	php vendor/bin/phpdoc

.PHONY: clean
clean:
	rm -rf intermediate
	rm -rf dist/apidocs
	rm -rf vendor

.PHONY: inc_version
inc_version:
	node scripts/inc_version

# Packagist looks for new tags in the git repo to find newly published
# packages
.PHONY: publish
publish: inc_version
	git add .
	git commit -m "Increment version to $(shell cat VERSION)"
	git tag $(shell cat VERSION)
	git push -u origin master
	git push -u origin master --tags

# An internal LightStep target for regenerating the thrift protocol files
.PHONY: thrift
thrift:
	thrift -r -gen php -out thrift $(LIGHTSTEP_HOME)/go/src/crouton/crouton.thrift
