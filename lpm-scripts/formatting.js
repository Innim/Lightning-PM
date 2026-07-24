/**
 * Attach the rich issue-link hover preview (Bootstrap popover) to every
 * [data-tooltip="issue"] element inside `root` that does not have one yet.
 *
 * Call this on dynamically inserted content (a newly posted comment, the
 * comment preview pane) so its issue links get the preview too: the delegated
 * global tooltip in lightning.js skips [data-tooltip] elements, so without an
 * explicit popover such links would show nothing at all.
 *
 * @param {Element|Document|jQuery} [root=document] container to scan
 */
function initIssueLinkPreviews(root) {
    $(root || document).find('[data-tooltip="issue"]').each(function () {
        const el = this;
        const $el = $(el);

        // Already initialized (e.g. re-scanned container) — skip.
        if (bootstrap.Popover.getInstance(el)) {
            return;
        }

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
}

$(function ($) {
    initIssueLinkPreviews(document);
});
