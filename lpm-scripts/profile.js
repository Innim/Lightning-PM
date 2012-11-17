$(document).ready(
    function ()
    {
        $( '#issueView .comments form.add-comment' ).hide();
                
        states.addState( $("#profileInfo"), '', profilePage.onShowInfo );
        //states.addState( $("#" ), 'edit', profilePage. );
        states.addState( $("#userSettings" ), 'settings', profilePage.onShowSettings );
                
        states.updateView();
    }
);

var profilePage = {};
profilePage.showInfo = function () {
    window.location.hash = '';
    states.updateView();
    return false;
};

profilePage.showSetting = function () {
    window.location.hash = 'settings';
    states.updateView();
    return false;
};

profilePage.onShowInfo = function() {
    $( '#profilePanel > h3' ).text( 'Информация' );
};

profilePage.onShowSettings = function() {
    $( '#profilePanel > h3' ).text( 'Настройки' );
};

profilePage.saveEmailPref = function () {            
    preloader.show();

    srv.profile.emailPref( 
        $( "#userSettings form input[name=seAddIssue]" ).is(':checked'), 
        $( "#userSettings form input[name=seEditIssue]" ).is(':checked'), 
        $( "#userSettings form input[name=seIssueState]" ).is(':checked'), 
        $( "#userSettings form input[name=seIssueComment]" ).is(':checked'),     
        function (res) {
            //btn.disabled = false;
            preloader.hide();
            if (res.success) {
                if ($( "#userSettings form input[name=seAddIssue]" ).is(':checked'))
                    $( "#userSettings form input[name=seAddIssue]" ).attr('checked', 'checked');
                else
                    $( "#userSettings form input[name=seAddIssue]" ).removeAttr('checked');
                
                if ($( "#userSettings form input[name=seEditIssue]" ).is(':checked'))
                    $( "#userSettings form input[name=seEditIssue]" ).attr('checked', 'checked');
                else
                    $( "#userSettings form input[name=seEditIssue]" ).removeAttr('checked');
                
                if ($( "#userSettings form input[name=seIssueState]" ).is(':checked'))
                    $( "#userSettings form input[name=seIssueState]" ).attr('checked', 'checked');
                else
                    $( "#userSettings form input[name=seIssueState]" ).removeAttr('checked');
               
                if ($( "#userSettings form input[name=seIssueComment]" ).is(':checked'))
                    $( "#userSettings form input[name=seIssueComment]" ).attr('checked', 'checked');
                else
                    $( "#userSettings form input[name=seIssueComment]" ).removeAttr('checked');
                
                messages.info( 'Сохранено' );
            } else {
                srv.err( res );
                $( "#userSettings form button[type=submit]" ).click();
            }
        }  
    );
    return false;
};