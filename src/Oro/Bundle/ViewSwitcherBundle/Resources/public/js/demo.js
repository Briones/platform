define(function(require, exports, module) {
    'use strict';

    require('oroui/js/extend/polyfill');
    require('app-modules!');

    var DeviceSwitcherApp;
    var _ = require('underscore');
    var $ = require('jquery');
    var DeviceSwitcherView = require('oroviewswitcher/js/app/views/device-switcher-view');
    var demoPageModelService = require('oroviewswitcher/js/app/services/demo-page-model-service');
    var DemoPopupView = require('oroviewswitcher/js/app/views/demo-popup-view');
    var DemoHelpCarouselView = require('oroviewswitcher/js/app/views/demo-help-carousel-view');
    var DemoLogoutButtonView = require('oroviewswitcher/js/app/views/demo-logout-button-view');

    var config = module.config();
    var pageModel = demoPageModelService.getModel();
    pageModel.set(_.pick(config, 'personalDemoUrl', 'projectName'));

    DeviceSwitcherApp = new DeviceSwitcherView({
        _sourceElement: $('<div class="demo-page" />').appendTo('body'),
        pageModel: pageModel
    });

    DeviceSwitcherApp.demoHelpCarouselView = new DemoHelpCarouselView({
        container: '.head-panel__col--center',
        model: pageModel
    });

    DeviceSwitcherApp.pageView.subview('logoutButton', new DemoLogoutButtonView({
        el: DeviceSwitcherApp.pageView.$('[data-role="control-action"]'),
        model: pageModel
    }));

    if (pageModel.get('isLoggedIn')) {
        DeviceSwitcherApp.demoHelpCarouselView.openIfApplicable();
    } else {
        pageModel.once('change:isLoggedIn', function() {
            DeviceSwitcherApp.demoHelpCarouselView.openIfApplicable();
        });
    }

    if (DemoPopupView.isApplicable()) {
        DeviceSwitcherApp.demoPopupView = new DemoPopupView({
            container: 'body',
            url: pageModel.get('personalDemoUrl')
        });
    }

    return DeviceSwitcherApp;
});
