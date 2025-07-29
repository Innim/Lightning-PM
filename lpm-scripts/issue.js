$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();
        $('[data-tooltip="issue"]').tooltip({
            tooltipClass: 'tooltip-link-issue',
            position: {
                my: "center bottom-20",
                at: "center top",
                using: function (position, feedback) {
                    $(this).css(position);
                    $("<div>")
                        .addClass("arrow")
                        .addClass(feedback.vertical)
                        .addClass(feedback.horizontal)
                        .appendTo(this);

                },
            },
            content: function () {
                const span = $('<span>').addClass('tooltip-link-issue-title').text($(this).prop('title'));
                const div = $('<div>').addClass('tooltip-link-issue-container').append(span);
                const imageUrl = $(this).data('img');
                if (imageUrl) {
                    const image = $('<img>').addClass('tooltip-link-issue-image').attr('src', imageUrl);
                    div.append(image);
                }
                return div
            },
        });

        states.addState($("#issueView"));
        states.addState($("#issueForm"), 'edit', issueForm.handleEditState);

        /*$( "#issueInfo li .priority-val" ).css( 
                'backgroundColor', 
                issuePage.getPriorityColor( $( "#issueInfo li input[name=priority]" ).val() ) 
        );*/
    }
);

function showMain() {
    window.location.hash = '';
    states.updateView();
};
