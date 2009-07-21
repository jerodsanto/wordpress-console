(function($){
	$.consoleController = function(){
		this.version = '0.2.1';
		this.counter = 0;
		this.queries = [];
		this.historyCounter = 0;
		
		var self = this;

		this.init = function() {
		    self.url    = WP_CONSOLE_URL;
		    self.secret = WP_CONSOLE_SECRET;

			// create shell div
			self.shell = $('<div id="shell"></div>').appendTo($('#wrapper'));

			// listen for clicks on the shell
			self.shell.click(function() {
				self.shell.find('input.current').focus();
			});

			// listen for key presses (up, down, and tab)
			$(document).keyup(function(e) {

				// get key code
				var key   = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
				// get current input
				var input = self.shell.find('input.current:last');

				switch (key) {
					case 38: // up
						lastQuery = self.queries[self.historyCounter-1];
						if (typeof lastQuery != "undefined") {
							self.historyCounter--;
							input.val(lastQuery).focus();
						}
						break;
					case 40: // down
						nextQuery = self.queries[self.historyCounter+1];
						if (typeof nextQuery != "undefined") {
							self.historyCounter++;
							input.val(nextQuery).focus();
						}  else {
							self.historyCounter = self.queries.length;
	 			           input.val("");
				        }
						break;
					} // switch
			});

			$(document).keydown(function(e) {
			    // get key code
				var key   = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
				
				// get current input
				var input = self.shell.find('input.current:last');

				switch (key) {
					case 38:
					case 40:
						e.preventDefault();
						break;
					case 9: // tab
						var lastval = input.val();
						e.preventDefault(); // don't do browser default action
						
						self.postJSON('complete', lastval, {
							success:  function(j) {
								if (self.check(j)) {
									// if returned array only has one element, use it to fill current input
									if (j.length == 1) {
										input.val(j).focus();
									} else {
										// print 3-column listing of array values
										buffer_to_longest(j);
										while (j.length > 0) {
											var line = j.splice(0,3);
											self.print(line.join(" "));
										}
										self.doPrompt();
										self.shell.find('input.current:last').val(lastval);
										}
									}
								}	
							});
			          break;
				}

			});

	    // reload the session stuff before getting started
		self.reload();

	    self.about();
	    self.doPrompt();
		}

		this.doPrompt = function(prompt) {

			// increment prompt counter
			self.counter++;
			
			// reset historyCounter
			self.historyCounter = self.counter;

			// default prompt to >> unless passed in as argument
			prompt = (prompt) ? prompt : ">>";

			// append prompt to shell
			var $row      = $('<div class="row" id="' + self.counter + '"></div>' );
			var $prompt   = $('<span class="prompt">' + prompt + '</span>');
			var $form     = $("<form></form>");
			var $input    = $("<input class='current' type='text' />");
			
			$form.append( $input );			
			$row.append( $prompt ).append( $form );
			
			self.shell.append( $row );

			// determine input width
			var input_width = self.shell.width() - 50;
			
			// set width and focus input
			$input.width( input_width ).focus();

			// listen for submit
			$form.submit(function(e) {
			  	var val = $input.val();

				// do not use normal http post
				e.preventDefault();

				// save in history and handle accordingly
	      		$input.removeClass("current");
	
				self.queries[self.counter] = val;
	
	      		switch(val) {
			        case "clear": case "c":
			          self.$header.siblings().empty();
			          self.doPrompt()
			          break;
			        case "help": case "?":
			          self.print("\nWhat's New:\n" +
			                      "  Tab-completion. Start a command and hit tab to see your options!\n" +
								  "\n" + 
			                      "Special Commands:\n" + 
			                      "  clear  (c) = clears the console output\n" +
			                      "  help   (?) = prints this help text\n" +
			                      "  reload (r) = flushes all variables and partial statements");
			          self.doPrompt()
			          break;
			        case "reload": case "r":
						self.reload(true);
						break;
			        default:
			          	self.postJSON('query', val, {
							success:  function(j) {
				              // if result is not an error
				              if (self.check(j)) {
				                // print output and return value if they exist
				                if (typeof j.rval != "undefined") {
				                  self.print("=> " + j.rval);
				                }
				                if (typeof j.output != "undefined") {
				                  if (j.output == "partial") {
				                    var p = "..";
				                    self.print('');
				                  } else {
				                    self.print(j.output);
				                  }
				                }
				              }
				              self.doPrompt((typeof p != "undefined") ? p : null);
				            },
				            error:  function() {
				              self.error("Most likely syntax. Forget the semicolon? If not, try 'reload' and re-execute");
				              self.doPrompt();
				            }
						});
			        } // end case
				});

		}
		
		this.reload = function(show_message){
			var callbacks = {};
			if(show_message === true){
				callbacks.success = function(j) {
		            self.print(j.output);
		            self.doPrompt();
		        }
			}
			return this.postJSON('reload',callbacks);
		}
		
		this.postJSON = function(page, value, callbacks, additional_data){
			var request = {
				type:     'POST',
				dataType: 'json',
				data: {}
			};
			var key = null;
			
			if(typeof(value) == "object") {
				additional_data = callbacks;
				callbacks = value;
			} 
			
			if(callbacks)       request = $.extend(request, callbacks);
			if(additional_data) request = $.extend(request, additional_data);
			
			switch(page){
				case "query":
					key = "query";
					break;
				case "complete":
					key = "partial";
					break;
			}
			
			request.url = self.url + page + ".php";
			
			if(key) {
				request.data[key] = value;
				request.data.signature = hex_hmac_sha1( self.secret, value );
			} else {
				request.data.reload = true;
			}
			
			return $.ajax(request);
			
		}

		this.about = function() {
		  self.$header  = $('<div id="header">' + 
		            		'WordPress Console [' + self.version + '] by ' + 
							'<a target="_blank" href="http://jerodsanto.net">Jerod Santo</a>' +
		            		'</div>');
		  this.shell.append(self.$header);
		}

		this.print = function(string) {
			// Using text() escapes HTML to output visible tags
			var result = $('<pre></pre>').text(string);
			this.shell.append( $('<div></div>').append(result) );
		}

		this.error = function(string) {
		  this.shell.append('<div class="err">Error: ' + string + '</div>');
		}

		this.check = function(json) {
			// make sure json result is not an error
			if (typeof json.error != "undefined") {
				this.error(json.error);
				return false;
			} else {
				return true;
			}
		}
		
		// Trigger init
		this.init();

	}
	$(document).ready(function() { window.consoleController = new $.consoleController(); });
})(jQuery);

// HELPER FUNCTIONS
function buffer_to_longest(array) {
 var longest = array[0].length;
  for (var i=1; i < array.length; i++) {
    if (array[i].length > longest)
      longest = array[i].length;
  };
  
  for (var i=0; i < array.length; i++) {
    array[i] = pad(array[i],longest);
  };
  
  return array;
}


function pad(string,length) {
  while (string.length < length) {
    string = string + " ";
  }
  return string;
}