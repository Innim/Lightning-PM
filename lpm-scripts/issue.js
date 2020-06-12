$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();

        states.addState($("#issueView"));
        states.addState($("#issueForm"), 'edit', issuePage.setEditInfo);

        states.updateView();

        /*$( "#issueInfo li .priority-val" ).css( 
                'backgroundColor', 
                issuePage.getPriorityColor( $( "#issueInfo li input[name=priority]" ).val() ) 
        );*/

        if ($('#issueView .comments .comments-list .comments-list-item').size() == 0)
            $('#issueView .comments .links-bar a.toggle-comments').hide();
    }
);

function showMain() {
    window.location.hash = '';
    states.updateView();
};
