
require('./sass/_app.scss');

// ------ Gppro itself ------
require('./js/GpproWebLoader.js');
global.GpproPaginatedBoxWidget = require('./js/widgets/GpproPaginatedBoxWidget').default;
global.GpproReloadPageWidget = require('./js/widgets/GpproReloadPageWidget').default;
global.GpproColor = require('./js/widgets/GpproColor').default;
global.GpproStorage = require('./js/widgets/GpproStorage').default;
require('./js/widgets/SidebarToggle').default.init();
