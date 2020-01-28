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
        
        if ($( '#issueView .comments .comments-list .comments-list-item' ).size() == 0) 
            $( '#issueView .comments .links-bar a.toggle-comments' ).hide();

        function highlightComment() 
        {
            var hash = window.location.hash;
            if (hash.substr(0, 9) === '#comment-')
            {
                 $( "#issueView .comments-list .comments-list-item" ).has("a.anchor[id="+hash.substr(1)+"]")
                    .find(".text").css("backgroundColor","#868686")
                    .animate({ backgroundColor: "#eeeeee" }, 1200);
            }
        }

        if ("onhashchange" in window) window.onhashchange = highlightComment;
    }
);

function showMain() {
    window.location.hash = '';
    states.updateView();
};

