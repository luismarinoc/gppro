
// NOTE: global key "KimaiCalendar" intentionally kept as the legacy name — it is the external ABI
// consumed directly by templates/calendar/user.html.twig ("new KimaiCalendar(...)"), which is
// explicitly out of scope for this PR (deferred to chunk 5b together with window.kimai).
global.KimaiCalendar = require('./js/widgets/GpproCalendar').default;
