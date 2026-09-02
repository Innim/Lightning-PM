$(function ($) {
    // Настройка вебхуков обходит все репозитории таска, по паре запросов в GitLab
    // на каждый, поэтому у неё свой инвокер: с общими 30 секундами браузер бросит
    // ждать раньше, чем сервер закончит.
    const webhooksInvoker = new ru.vbinc.net.F2PInvoker(srv.gateway);
    webhooksInvoker.setTimeout(300);

    srv.admin = {
        s: new BaseService('AdminService'),
        sWebhooks: new BaseService('AdminService', webhooksInvoker),
        flushCache: function () {
            this.s._('flushCache');
        },
        saveSettings: function (data, onResult) {
            this.s._('saveSettings');
        },
        applyDbMigrations: function (onResult) {
            this.s._('applyDbMigrations');
        },
        setupGitlabWebhooks: function (onResult) {
            this.sWebhooks._('setupGitlabWebhooks');
        },
    };
});