
require('./sass/_app.scss');

// ------ Kimai itself ------
require('./js/GpproWebLoader.js');
// NOTE: global keys "KimaiPaginatedBoxWidget"/"KimaiReloadPageWidget" intentionally kept as legacy
// names — they are the external ABI consumed directly by several .twig templates
// (e.g. KimaiReloadPageWidget.create(...), KimaiPaginatedBoxWidget.create(...)), which are
// explicitly out of scope for this PR (deferred to chunk 5b together with window.kimai).
global.KimaiPaginatedBoxWidget = require('./js/widgets/GpproPaginatedBoxWidget').default;
global.KimaiReloadPageWidget = require('./js/widgets/GpproReloadPageWidget').default;
global.GpproColor = require('./js/widgets/GpproColor').default;
global.GpproStorage = require('./js/widgets/GpproStorage').default;
require('./js/widgets/SidebarToggle').default.init();
