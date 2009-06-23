var consoleController = {

	counter : 0,

	queries : [],

	historyCounter : 0,

	init : function() {

		var self = this;
		
		self.url = '/wp-content/plugins/wordpress-console/';

		// create shell div
		jQuery('#wrapper').append('<div id="shell"></div>');
		self.shell = jQuery('#shell');

		// listen for clicks on the shell
		self.shell.click(function() {
			self.shell.find('input').focus();
		});

		// listen for key presses (up, down, and tab)
		jQuery(document).keydown(function(e) {

			// get key code
			var key = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;

			switch (key) {
				case 38: // up
					lastQuery = self.queries[self.historyCounter-1];
					if (typeof lastQuery != "undefined") {
						self.historyCounter--;
						self.shell.find('input').val(lastQuery);
					}
					break;
				case 40: // down
					nextQuery = self.queries[self.historyCounter+1];
					if (typeof nextQuery != "undefined") {
						self.historyCounter++;
						self.shell.find('input').val(nextQuery);
					}
					break;
				case 9: // tab
					partial = self.shell.find('input').val();
					// complete partial query (make sure it's a normal input, not a username/password field)
					if (self.shell.find('input').parent().parent().attr('class') == 'row') {
						jQuery.getJSON('complete.php', {partial: partial, PHPSESSID: self.PHPSESSID}, function(json) {
							// replace partial with complete and restore focus
							self.shell.find('input').val(json.result).focus();
						});
					}
			}
			
		});

		// watch input field
		jQuery(document).keypress(function() { self.inputSize(); });

		// license
		self.print("WordPress Console v0.1.0 by Jerod Santo &lt;http://jerodsanto.net&gt;");
    
    self.doInit();
	},
	
	doInit : function() {
	  
	  var self = this;
	  
	  jQuery.ajax({
	    url:      self.url + 'init.php',
	    type:     'POST',
	    dataType: 'json',
	    data: {
	      init: true
	    },
	    success: function(j) {
	      self.PHPSESSID = j.PHPSESSID;
	      self.user = j.user;
	      self.wp_version = j.wp_version;
	      self.doPrompt();
	    }
	  })
	},

	doPrompt : function() {

		var self = this;

		// increment prompt counter
		self.counter++;
		// reset historyCounter
		self.historyCounter = self.counter;
		
		// prompt text
		prompt = self.user + '@' + self.wp_version + '$';
		// append prompt to shell
		self.shell.append('<div class="row" id="' + self.counter + '"><span class="prompt">' + prompt + '</span><form><input type="text" /></form></div>');
		// focus input
		self.shell.find('div#' + self.counter + ' input').focus();
		
		// listen for submit
		self.shell.find('div#' + self.counter + ' form').submit(function(e) {
		
			// do not use normal http post
			e.preventDefault();
			
			// if input field is empty, don't do anything
			if (self.shell.find('div#' + self.counter + ' input').val() == '') return false;
			
			// send ajax request
			jQuery.ajax({
				url:      self.url + 'query.php',
				type:     'POST',
				dataType: 'json',
				data : {
					query:      self.shell.find('div#' + self.counter + ' input').val(),
					PHPSESSID:  self.PHPSESSID
				},
				success: function(j) {
				
					// if result is not an error
					if (self.check(j)) {
				
						// if result is not javascript
						if (typeof j.javascript == 'undefined') {
							// print result to shell
							self.print(j.result);
						} else {
							// execute javascript
							eval(j.result);
						}
				
						// get value of query
						val = self.shell.find('input').val();
						// replace input with query
						self.shell.find('input').parent().empty().append(val);
				
            // save query
            self.queries[self.counter] = val;
          }
          // do another prompt
          self.doPrompt(j);

					}
				
			});

		});

	},

	print : function(string) {
		this.shell.append('<div class="result"><pre>' + string + '</pre></div>');
	},

	check : function(json) {

		// make sure json result is not an error
		if (typeof json.error != "undefined") {
			this.shell.append('<div class="error">Error: ' + json.error + '</div>');
			return false;
		} else {
			return true;
		}

	},

	inputSize : function() {
		// increase the size of the input box when the user types more
		this.shell.find('input').attr('size', (this.shell.find('input').val().length + 5)).focus();
	},

	destruct : function() {} // ... 
	
}
jQuery(document).ready(function() { consoleController.init(); });
