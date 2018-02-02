$(document).ready(
  function () {
      issuesExport2Excel.window = $("#issuesExportToExcel").
      	  dialog({autoOpen:false, modal:true, resizable:false, width:450});
  }
);

var issuesExport2Excel = {
	window: null,
	projectId: null,
	openWindow: function (projectId) {
		issuesExport2Excel.projectId = projectId;
		issuesExport2Excel.window.dialog('open');
	},
	closeWindow: function () {
		issuesExport2Excel.projectId = null;
		issuesExport2Excel.window.dialog('close');
	},
	export: function () {
		var projectId = issuesExport2Excel.projectId;
		if (!projectId)
			return;

		var fromDate = $("#issuesExportForm input[name=fromDate]").val();
		var toDate = $("#issuesExportForm input[name=toDate]").val();

		if (fromDate == '' || toDate == '') {
			messages.alert('Необходимо указать период для выгрузки.');
			return;
		}
		//issuesExport2Excel.closeWindow();
		preloader.show();
		srv.issue.exportCompletedIssuesToExcel(
			projectId,
			fromDate,
			toDate,
			function (res) {
				preloader.hide();
				if (res.success) {
					// alert(res.fileUrl);
					window.open(res.fileUrl, '_blank');
				} else {
					srv.err(res);
				}
			});
// srv.issue.comment( 
// issueId, 
// text, 
// function (res) {
//     preloader.hide();
//     if (res.success) {
//         $( '#issueView .comments form.add-comment textarea[name=commentText]' ).val( '' );
//         $( '#issueView .comments ol.comments-list' ).prepend( 
//                '<li>' +  
//                 '<img src="' + res.comment.author.avatarUrl + '" class="user-avatar small"/>' +
//                 '<p class="author">' + res.comment.author.linkedName + '</p> ' +
//                 '<p class="date"><a class="anchor" id="'+res.comment.id+
//                 '"href="#comment-'+res.comment.id+'">'+res.comment.dateLabel+'</a></p>' +
//                 '<p class="text">' + res.comment.text + '</p>' +
//                '</li>' 
//         );
//         issuePage.hideCommentForm();
//         $( '#issueView .comments .links-bar a.toggle-comments' ).show();
        
//         if (!$( '#issueView .comments .comments-list' ).is(':visible')) 
//             issuePage.toogleCommentForm();
//     } else {
//         srv.err(res);
//     }
// } 
// );
	}
};