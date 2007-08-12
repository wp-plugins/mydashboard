var myDash = jQuery.noConflict();

function closegadget() {
	if(confirm('Are you sure you want to remove this gadget?')) {
		myDash(this).parent().parent().fadeOut('slow', function() {myDash(this).remove(); myDash().myDashRemoveGadget(this.id);});
	}
	return false;
}

function togglegadget() {
	myDash(this).parent().parent().find('.myboxinner').slideToggle('fast');
	return false;
}

function toggleedit() {
	myDash(this).parent().parent().find('.myboxedit').slideToggle('fast');
	return false;
}

function editupdate() {
	// submit the data to the webserver and
	// get the new content
	myDash(this).myDashSendEdit();
	return false;
}

function togglelibrary() {
	myDash('#mydashlibrary').slideToggle('fast');
	if(myDash(this).html() == "Add Gadgets") {
		myDash(this).html('Hide Library');
	} else {
		myDash(this).html('Add Gadgets');
	}
	return false;
}

function mydashboardReady() {

	myDash('div.droppable').Sortable(
			{
				opacity: 	0.5,
				accept: 'mybox',
				helperclass: 'sortHelper',
				activeclass : 	'sortableactive',
				hoverclass : 	'sortablehover',
				handle: '.mytitle',
				tolerance: 'pointer',
				onChange : function(ser)
				{
					// Call the move function here
					if(ser.length == 1) {
						// reorder in a single column
						serial = myDash.SortSerialize(ser[0].id);
						myDash().myDashReorderGadget(ser[0].id, serial.hash);
					} else {
						// move from one column to another
						serial1 = myDash.SortSerialize(ser[0].id);
						serial2 = myDash.SortSerialize(ser[1].id);
						myDash().myDashMoveGadget(ser[0].id, serial1.hash,ser[1].id, serial2.hash);
					}
				},
				onStart : function()
				{
					myDash.iAutoscroller.start(this, document.getElementsByTagName('body'));
				},
				onStop : function()
				{
					myDash.iAutoscroller.stop();
				}
			}
		);

	myDash('a.delbox').click(closegadget);
	myDash('a.minbox').click(togglegadget);
	myDash('a.editbox').click(toggleedit);
	myDash('form.myboxeditform').submit(editupdate);
	
	myDash('#addgadgets').click(togglelibrary);
	myDash('#mydashlibrary').myDashLoadLibrary();
	
	myDash(".loadingbox").ajaxStart(function(){
   		myDash(this).show();
 	});
 	myDash(".loadingbox").ajaxStop(function(){
   		myDash(this).hide();
 	});
	
}

myDash(document).ready(mydashboardReady);