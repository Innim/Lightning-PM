$(document).ready(
    function ()
    {
        $( '#issueView .comments form.add-comment' ).hide();
                
        states.addState( $("#issueView") );
        states.addState( $("#issueForm" ), 'edit', issuePage.setEditInfo );
                
        states.updateView();
        
        /*$( "#issueInfo li .priority-val" ).css( 
                'backgroundColor', 
                issuePage.getPriorityColor( $( "#issueInfo li input[name=priority]" ).val() ) 
        );*/
        
        if ($( '#issueView .comments .comments-list > li' ).size() == 0) 
            $( '#issueView .comments .links-bar a.toggle-comments' ).hide();
    }
);

function showMain() {
    window.location.hash = '';
    states.updateView();
};

// Меню скопировать комит сообщение 
$(document).ready(
    function ()
    {
        $('a.link').zclip(
        {
            path : 'http://lightning-pm/lpm-scripts/libs/ZeroClipboard.swf',
            copy : function()
                   { 
                        var a = $('.issue-id').text();
                        var b = $('.issue-name').text();
                        return 'Issue # '+a+ ' : '+ b;                
                   }
        });
    }
);