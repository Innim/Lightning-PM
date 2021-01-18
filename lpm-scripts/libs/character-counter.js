//jQuery inpu field character counter
$(document).ready(function () {
	/**
	* Checks for character and sets info for input
	*
	*/
	$(".count-chars").keyup(function () {
		//get input value and length
		var charInput = this.value;
		var charInputLength = this.value.length;

		//get data values
		const maxChars = $(this).data("chars-max");
		const messageColor = $(this).data("msg-color");

		//get input id and set input message id
		var inputId = this.getAttribute('id');
		var messageDivId = inputId + "Message";

		//set default message for message div
		var remainingMessage = "";

		if (charInputLength >= maxChars) {
			//limit chars to max set
			$("#" + inputId).val(charInput.substring(0, maxChars));
			remainingMessage = "0";
		} else {
			remainingMessage = (maxChars - charInputLength);
		}


		//check if message div exists
		if ($("#" + messageDivId).length == 0) {
			//set div with message
			$('#' + inputId).after('<div id="' + messageDivId + '" class="text-' + messageColor + ' font-weight-bold">' + remainingMessage + '</div>');
		}
		else {
			//update div message 
			$("#" + messageDivId).text(remainingMessage);
		}
	});
});
