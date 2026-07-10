# Court Side live preview

Boots the ClubHouse engine without WordPress and renders the Home shell.

```bash
# from the plugin root (docroot = plugin root)
php -S localhost:8124
# open http://localhost:8124/preview/
```

The bottom-right swatches re-theme the page via the real colour engine (every
derived token updates, not just the base accent). This same `Page_Renderer`
output is what WordPress `template_include` will echo later; this harness is the
early, DB-free way to watch progress and the basis for the CI preview URL.
