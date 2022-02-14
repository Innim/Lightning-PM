$(function ($) {
    srv.admin = {
        s: new BaseService('AdminService'),
        flushCache: function () {
            this.s._('flushCache');
        },
    };
});