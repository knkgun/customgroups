# Frontend rules

HANDLEBARS=$(NODE_PREFIX)/node_modules/.bin/handlebars

template_src=$(wildcard js/templates/*.handlebars)
clean_rules+=clean-templates
js_rules+=templates
help_rules+=help-frontend
js_namespace=OCA.$(app_namespace)

.PHONY: help-frontend
help-frontend:
	@echo -e "Frontend development:\n"
	@echo -e "watch\t\tto watch for changes and rebuild all templates"
	@echo -e "templates\tto manually build all templates"
	@echo -e "clean-templates\tto clean generated templates"
	@echo

# add compiled templates to sources
all_src+=$(addsuffix .js, $(template_src))

$(HANDLEBARS): $(nodejs_deps)

%.handlebars.js: %.handlebars $(HANDLEBARS)
	$(HANDLEBARS) -n "$(js_namespace).Templates" $< > $@

.PHONY: templates
templates: $(addsuffix .js, $(template_src))

.PHONY: clean-templates
clean-templates:
	rm -f $(addsuffix .js, $(template_src))

#
# Watch
#
.PHONY: watch
watch: $(nodejs_deps)
	$(NPM) --prefix $(NODE_PREFIX) run watch
