$(function ($) {
    srv.admin = {
        s: new BaseService('AdminService'),
        flushCache: function () {
            this.s._('flushCache');
        },
        saveSettings: function (data, onResult) {
            this.s._('saveSettings');
        },
    };
});