$(document).ready(
  function ()
  {
      $( "#usersChooser" ).dialog( {autoOpen:false, modal:true, resizable:false} );

      $('#btnSelectMember').click(function (event) {
          var memberByDefaultId = $('#selectMember').val();

          // Если Исполнитель не выбран, но кнопка нажата, сбрасываем
          if (memberByDefaultId === "0") {
              return event.preventDefault();
          }
          srv.project.addIssueMemberDefault(
              $( "#projectMembers input[name=projectId]" ).val(),
              memberByDefaultId,
              function(res) {
                  if(res.success) {
                      location.reload();
                  } else {
                      srv.err(res);
                  }
              }
          );
      });

      $('.delete-member-default-i').click(function() {
          srv.project.deleteMemberDefault(
              $("#projectMembers input[name=projectId]").val(),
              function (res) {
                  if (res.success) {
                      location.reload();
                  } else {
                      srv.err(res);
                  }
              }
          )
      });

      // Добавляем тестера.
      $('#btnSelect').click(function (event) {
          var userId = $('#selectTester').val();

          if (userId === "0") {
              return event.preventDefault();
          }

          srv.project.addTester(
              $( "#projectMembers input[name=projectId]" ).val(),
              userId,
              function(res) {
                  if( res.success ) {
                      location.reload();
                  } else {
                      srv.err(res);
                  }
              }
          );
      });
      
      // Удаляем тестера.
      $('.delete-tester-i').click(function() {
          srv.project.deleteTester(
              $( "#projectMembers input[name=projectId]" ).val(),
              function(res) {
                  if(res.success) {
                      location.reload();
                  } else {
                      srv.err(res);
                  }
              }
          );

      });
  }
);

/**
 * 
 * @param {Array} checked
 * @param {Function} onComplete
 * @returns {openUsersChooser}
 */
function ucOpen( checked, onComplete ) {
    $( "#usersChooser" ).dialog( 'open' );
    var checkboxes = $( "#usersChooser table.users-list > " +
            "tbody > tr > td > input[type=checkbox][name=userId]" )
    .removeAttr( 'checked' )
    .removeAttr( '_hidden' );
    $( "#usersChooser table.users-list > tbody > tr" ).show();
    if (checked && checked.length > 0) {
        var j = 0;
        for (var i = 0; i < checkboxes.length; i++) {            
            if (checkboxes.eq( i ).attr( 'checked' )) userIds.push( checkboxes.eq( i ).val() );
            //if (Array.indexOf( checked, checkboxes.eq( i ).val() ) != -1) checkboxes.eq( i ).attr( 'checked', 'checked' );
            for (j = 0; j < checked.length; j++) {
                if (checked[j].toString() == checkboxes.eq( i ).val()) {
                    //checkboxes.eq( i ).attr( 'checked', 'checked' );
                    checkboxes.eq( i ).attr( '_hidden', true );
                    checkboxes.eq( i ).parent().parent().hide();
                    break;
                }
            }
        }
    }
    ucOpen.onComplete = onComplete;
}

function ucInput() {
    var pattern = $( "#usersChooser input[name=usersFilter]" ).val();
    if (ucInput.pattern == pattern) return;
    ucInput.pattern = pattern;
   // if (pattern != '') {
        //console.log( pattern );
        $( "#usersChooser table.users-list > tbody > tr" ).hide();
        //$( "#usersChooser table.users-list > tbody > tr:has( td:first:contains('" + pattern + "'))" ).show();
        var rows = $( "#usersChooser table.users-list > tbody > tr" );
        for (var i = 0; i < rows.length; i++) {            
            //if ($( "td:first", rows[i] ).html().toLowerCase().search( RegExp.escapeStr( pattern.toLowerCase() ) ) != -1) rows.eq( i ).show();
            if ((pattern == '' || $( "td:first", rows[i] ).html().
                    search( RegExp.createFromStr( pattern, 'i' ) ) != -1)
                && !rows.eq( i ).find( "input[type=checkbox][name=userId]" ).attr( '_hidden' )) rows.eq( i ).show();
        }        
   // } else {
   //     $( "#usersChooser table.users-list > tbody > tr" ).show();
   // }
    
}

function ucDone() {
    if (ucOpen.onComplete) {
        var userIds = [];
        var checkboxes = $( "#usersChooser table.users-list > tbody > tr > td > input[type=checkbox][name=userId]" );
        for (var i = 0; i < checkboxes.length; i++) {            
            if (checkboxes.eq( i ).attr( 'checked' )) userIds.push( checkboxes.eq( i ).val() );
        }
        if (userIds.length > 0)
        ucOpen.onComplete( userIds );
    }
    $( "#usersChooser" ).dialog( 'close' );
}
