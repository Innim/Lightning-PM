$(function ($) {
    $('[data-tooltip="issue"]').each(function () {
        const el = this;
        const $el = $(el);
        const idInProject = $el.data('id-in-project');
        const title = $el.attr('title');
        const imageUrl = $el.data('img');

        // Clear the native browser tooltip
        $el.removeAttr('title');

        const popover = new bootstrap.Popover(el, {
            trigger: 'hover focus',
            // trigger: 'manual',
            placement: 'top',
            container: 'body',
            html: true,
            content: function () {
                const content = $('<div>').addClass('tooltip-link-issue-container')
                if (imageUrl) {
                    const img = $('<img>').addClass('tooltip-link-issue-image').attr('src', imageUrl);
                    const wrapper = $('<div>').addClass('img-wrapper border border-1 rounded-3').append(img);
                    content.append(wrapper);
                    img.on('load', () => {
                        wrapper.addClass('done');
                        img.addClass('loaded');
                    });
                }
                content.append($('<span>').addClass('tooltip-link-issue-title').text(`${idInProject}. ${title}`));

                return content;
            },
        });

        el.addEventListener('hide.bs.popover', function () {
            // Force element to stay visible - some sort of bug in Bootstrap in conflict with jQuery
            this.style.display = '';
        });
    });
});
