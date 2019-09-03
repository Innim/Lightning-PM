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

        function highlightComment() 
        {
            var hash = window.location.hash;
            if (hash.substr(0, 9) === '#comment-')
            {
                 $( "#issueView ol.comments-list li" ).has("a.anchor[id="+hash.substr(1)+"]")
                    .find(".text").css("backgroundColor","#868686")
                    .animate({ backgroundColor: "#eeeeee" }, 1200);
            }
        }

        if ("onhashchange" in window) window.onhashchange = highlightComment;
        highlightComment();
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
        var clipboard = new ClipboardJS('.commit-message');

        clipboard.on('success', function(e) {
            if (window.getSelection) {
                window.getSelection().removeAllRanges();
            } else { // старый IE
                document.selection.empty();
            }
        });
    }
);

