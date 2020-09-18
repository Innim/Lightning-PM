$(document).ready(
    function () {
        $('#issueView .comments form.add-comment').hide();

        states.addState($("#issueView"));
        states.addState($("#issueForm"), 'edit', issueForm.handleEditState);

        states.updateView();

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
