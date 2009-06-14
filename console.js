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
						self.shell.find('input.current').val(lastQuery);
					}
					break;
				case 40: // down
					nextQuery = self.queries[self.historyCounter+1];
          self.historyCounter++;
					if (typeof nextQuery != "undefined") {
						self.shell.find('input.current').val(nextQuery);
					}  else {
            self.shell.find('input.current').val("");
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

		// about
		self.about();
    
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
		self.shell.append('<div class="row" id="' + self.counter + '"><span class="prompt">' + prompt + '</span><form><input class="current" type="text" /></form></div>');
		// focus input
		self.shell.find('div#' + self.counter + ' input').focus();
		
		// listen for submit
		self.shell.find('div#' + self.counter + ' form').submit(function(e) {
		  var input = self.shell.find('div#' + self.counter + ' input');
		  var val = input.val();
		
			// do not use normal http post
			e.preventDefault();
			
			// if input field is empty, don't do anything
			if (val == '') return false;
			
			// otherwise, save in history and handle accordingly
      input.removeClass("current");
			self.queries[self.counter] = val;
      switch(val) {
        case "clear": case "c":
          jQuery('#shell #header').siblings().empty();
          self.doPrompt()
          break;
        case "help": case "?":
          self.print("this is the help text");
          self.doPrompt()
          break;
        default:
          jQuery.ajax({
            url:      self.url + 'query.php',
            type:     'POST',
            dataType: 'json',
            data : {
              query:      val,
              PHPSESSID:  self.PHPSESSID
            },
            success: function(j) {
              // if result is not an error
              if (self.check(j)) {
                // print output and return value if they exist
                if (j.return.length > 0) {
                  self.print("=> " + j.return);
                }
                if (j.output.length > 0) {
                  self.print(j.output);
                }
                self.doPrompt();
              }
            }
          });
        } // end case
		});

	},
	
	about : function() {
	  var str = '<div id="header">' + 
	            'WordPress Console [0.1.0] by <a target="_blank" href="http://jerodsanto.net">Jerod Santo</a>' +
	            '</div>';
	  this.shell.append(str);
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
		this.shell.find('input.current').attr('size', (this.shell.find('input.current').val().length + 5)).focus();
	},

	destruct : function() {} // ... 
	
}
jQuery(document).ready(function() { consoleController.init(); });
