$(document).ready(
  function () {
      let projectId = $("#projectMembers input[name=projectId]").val();

      function reloadOnSuccees(res) {
        if (res.success) {
            location.reload();
        } else {
            srv.err(res);
        }
      }

      $("#usersChooser").dialog({autoOpen:false, modal:true, resizable:false});
      
      $('#saveMaster').click(function (event) {
          let masterId = $('#selectMaster').val();

          if (masterId == "0")
              return event.preventDefault();

          srv.project.setMaster(projectId, masterId, reloadOnSuccees);
      });

      $('#removeMaster').click(function() {
          srv.project.deleteMaster(projectId, reloadOnSuccees);
      });

      $('#btnSelectMember').click(function (event) {
          let memberByDefaultId = $('#selectMember').val();

          // Если Исполнитель не выбран, но кнопка нажата, сбрасываем
          if (memberByDefaultId === "0") {
              return event.preventDefault();
          }
          srv.project.addIssueMemberDefault(projectId, memberByDefaultId, reloadOnSuccees);
      });

      $('#removeDefaultMember').click(function() {
          srv.project.deleteMemberDefault(projectId, reloadOnSuccees);
      });

      // Добавляем тестера.
      $('#btnSelect').click(function (event) {
          let userId = $('#selectTester').val();

          if (userId === "0") {
              return event.preventDefault();
          }

          srv.project.addTester(projectId, userId, reloadOnSuccees);
      });
      
      // Удаляем тестера.
      $('#removeTester').click(function() {
          srv.project.deleteTester(projectId, reloadOnSuccees);
      });
  }
);

/**
 * 
 * @param {Array} checked
 * @param {Function} onComplete
 * @returns {openUsersChooser}
 */
function ucOpen(checked, onComplete) {
    $("#usersChooser").dialog('open');
    var checkboxes = $("#usersChooser input[type=checkbox][name=userId]")
      .removeAttr('checked')
      .removeAttr('_hidden');
    $("#usersChooser table.users-list > tbody > tr").show();
    if (checked && checked.length > 0) {
        var j = 0;
        for (var i = 0; i < checkboxes.length; i++) {            
            if (checkboxes.eq(i).prop('checked'))
              userIds.push(checkboxes.eq(i).val());
            for (j = 0; j < checked.length; j++) {
                if (checked[j].toString() == checkboxes.eq(i).val()) {
                    checkboxes.eq(i).attr('_hidden', true);
                    checkboxes.eq(i).parent().parent().hide();
                    break;
                }
            }
        }
    }
    ucOpen.onComplete = onComplete;
}

function ucInput() {
    var pattern = $("#usersChooser input[name=usersFilter]").val();
    if (ucInput.pattern == pattern)
      return;

    ucInput.pattern = pattern;
    $("#usersChooser table.users-list > tbody > tr").hide();
    var rows = $("#usersChooser table.users-list > tbody > tr");
    for (var i = 0; i < rows.length; i++) {            
      if ((pattern == '' 
        || $("td:first", rows[i]).html().search(RegExp.createFromStr(pattern, 'i')) != -1)
        && !rows.eq(i).find("input[type=checkbox][name=userId]").attr('_hidden'))
          rows.eq(i).show();
    }         
}

function ucDone() {
    if (ucOpen.onComplete) {
        var userIds = [];
        var checkboxes = $("#usersChooser input[type=checkbox][name=userId]");
        for (var i = 0; i < checkboxes.length; i++) {            
            if (checkboxes.eq(i).prop('checked'))
              userIds.push(checkboxes.eq(i).val());
        }
        if (userIds.length > 0)
          ucOpen.onComplete(userIds);
    }

    $("#usersChooser").dialog('close');
}
