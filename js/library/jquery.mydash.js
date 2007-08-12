/*
* myDashboard jQuery plugins
*
*/

jQuery.fn.myDashSendEdit = function() {
	return this.each(function () {
			var mythis = this;
			var inputs = [];
			var ginputs = [];
			
			var display = function(data) {
				if(data != '') {
					var gotback = jQuery('div:eq(0)',data).parent().attr('id');
					// parse the data here
					// add the content
					theinner = jQuery('.myboxinner',data).html();
					
					if(theinner != '') {
						jQuery('#' + gotback + ' .myboxinner').html(theinner);
					} else {
						jQuery('#' + gotback + ' .myboxinner').html('No data returned');
					}
					// update the title
					jQuery('#' + gotback + ' .mytitle').html(jQuery('.mytitle',data).html());
					// assign click handlers
					jQuery('#' + gotback + '#' + gotback + ' a.delbox').click(closegadget);
					jQuery(' a.minbox', mythis).click(togglegadget);
					jQuery('#' + gotback + ' a.editbox').click(toggleedit);
				} else {
					jQuery('#' + gotback + ' .myboxinner').html('No data returned');
				}	
			};
			
			jQuery(':input', this).each(function() {
						inputs.push(this.name + '=' + escape(this.value));
						});
			
			ginputs.push('call=' + escape('_ajax'));
			ginputs.push('action=' + escape('sendedit'));
			ginputs.push('nocache=' + escape(new Date().getTime()));
			
			jQuery.ajax({
   				type: "POST",
   				url: "index.php?" + ginputs.join('&'),
   				data: inputs.join('&'),
   				dataType: "html",
			   	success: display
			   });
	
		});
}

jQuery.fn.myDashLoadLibrary = function() {
	return this.each(function () {
			var mythis = this;
			var display = function(data) {
							if(data != '') {
								jQuery('#' + mythis.id).html(data);
								jQuery('a.addtopage').click(function() {jQuery('#'+this.id).myDashAddGadget(); return false;});
								}
							};
			jQuery.ajax({
   				type: "GET",
   				url: "index.php",
   				data: {call:"_ajax", action:'loadlibrary', nocache: new Date().getTime()},
   				dataType: "html",
			   	success: display
			   });
			});
}

jQuery.fn.myDashGetContent = function(options) {
	return this.each(function () {
					var mythis = this; // store the current item
					var update = function(data) {
									if(data != '') {
										// parse the data here
										// add the content
										theinner = jQuery('.myboxinner',data).html();
										if(theinner != '') {
											jQuery('.myboxinner', mythis).html(theinner);
										} else {
											jQuery('.myboxinner', mythis).html('No data returned');
										}
										// update the title
										jQuery('.mytitle', mythis).html(jQuery('.mytitle',data).html());
										// assign click handlers
										jQuery('a.delbox', mythis).click(closegadget);
										jQuery('a.minbox', mythis).click(togglegadget);
										jQuery('a.editbox', mythis).click(toggleedit);
									} else {
										jQuery('.myboxinner', mythis).html('No data returned');
									}
									};
					jQuery.ajax({
		   				type: "GET",
		   				url: "index.php",
		   				data: {call:"_ajax", action:"updatecontent", name:this.id, nocache: new Date().getTime()},
		   				dataType: "html",
		   				success: update
					   });		
					});
}

jQuery.fn.myDashReorderGadget = function(column, gadgets) {
	return this.each(function () {
					jQuery.ajax({
		   				type: "GET",
		   				url: "index.php",
		   				data: {call:"_ajax", action:'reordercolumn', column: column, gadgets: gadgets, nocache: new Date().getTime()},
		   				dataType: "html"
					   });
					});
}

jQuery.fn.myDashMoveGadget = function(fromcolumn, fromgadgets, tocolumn, togadgets) {
	return this.each(function () {
					jQuery.ajax({
		   				type: "GET",
		   				url: "index.php",
		   				data: {call:"_ajax", action:'movegadget', fromcolumn: fromcolumn, fromgadgets: fromgadgets, tocolumn: tocolumn, togadgets: togadgets, nocache: new Date().getTime()},
		   				dataType: "html"
					   });
					});
}

jQuery.fn.myDashRemoveGadget = function(gadget) {
	return this.each(function () {
					function reloadlibrary(data) {
						 myDash('#mydashlibrary').myDashLoadLibrary();
					}
					jQuery.ajax({
		   				type: "GET",
		   				url: "index.php",
		   				data: {call:"_ajax", action:'removegadget', gadget: gadget, nocache: new Date().getTime()},
		   				dataType: "html",
		   				success: reloadlibrary
					   });
					});
}

jQuery.fn.myDashAddGadget = function() {
	return this.each(function () {
					var mythis = this;
					jQuery('#'+mythis.id).html('Adding gadget...');
					// code to get a gadget instance
					var display = function(data) {
						
						if(data != "") {
							// need to get the id of the returned gadget
							// for some reason I can't work out how to get the id of the
							// enclosing div, so will get the next one and skip back to the parent
							// this needs to be fixed
							haveadded = jQuery('div:eq(0)',data).parent().attr('id');
							// add it to the first column and set it as draggable
							jQuery('#column-1')
						    	.append(data)
						    	.SortableAddItem(document.getElementById(haveadded));
							
							jQuery('#' + haveadded + ' a.delbox').click(closegadget);
							jQuery('#' + haveadded + ' a.minbox').click(togglegadget);
							jQuery('#' + haveadded + ' a.editbox').click(toggleedit);
							myDash('#' + haveadded + ' form.myboxeditform').submit(editupdate);
							
							myDash('#mydashlibrary').myDashLoadLibrary();
						}
					}
					jQuery.ajax({
		   				type: "GET",
		   				url: "index.php",
		   				data: {call:"_ajax", action:'addgadget', gadget: mythis.id, nocache: new Date().getTime()},
		   				dataType: "html",
		   				success: display
					   });
					});
}