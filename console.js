(function($){
	$.consoleController = {

		counter : 0,
		queries : [],
		historyCounter : 0,

		init: function() {

			var self = this;

	    self.url    = $('#wpconsoleurl').val();
	    self.secret = $("#wpconsolesecret").val()

			// create shell div
			$('#wrapper').append('<div id="shell"></div>');
			self.shell = $('#shell');

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
						e.preventDefault();
						break;
					case 40:
						e.preventDefault();
						break;
					case 9: // tab
						var lastval = input.val();
						e.preventDefault(); // don't do browser default action

		  				$.ajax({
		  				  url:      self.url + 'complete.php',
		  				  type:     'POST',
		  				  dataType: 'json',
		  				  data:     { partial: lastval, signature: hex_hmac_sha1( self.secret, lastval ) },
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
			$.ajax({
	      url:      self.url + 'reload.php',
	      type:     'POST',
	      dataType: 'json',
	      data:     { reload: true }
	    });

	    self.about();
	    self.doPrompt();
		},

		doPrompt: function(prompt) {

			var self = this;

			// increment prompt counter
			self.counter++;
			// reset historyCounter
			self.historyCounter = self.counter;

	    // default prompt to >> unless passed in as argument
			prompt = typeof(prompt) != "undefined" ? prompt : ">>";

			// append prompt to shell
			self.shell.append('<div class="row" id="' + self.counter + '"><span class="prompt">' + prompt + '</span><form><input class="current" type="text" /></form></div>');

			// determine input width
			var input_width = self.shell.width() - 50;
			// set width and focus input
			self.shell.find('div#' + self.counter + ' input').width(input_width).focus();

			// listen for submit
			self.shell.find('div#' + self.counter + ' form').submit(function(e) {
			  var input = self.shell.find('div#' + self.counter + ' input');
			  var val = input.val();

				// do not use normal http post
				e.preventDefault();

				// save in history and handle accordingly
	      input.removeClass("current");
				self.queries[self.counter] = val;
	      switch(val) {
	        case "clear": case "c":
	          $('#shell #header').siblings().empty();
	          self.doPrompt()
	          break;
	        case "help": case "?":
	          self.print("\nWhat's New:\n" +
	                      "  Tab-completion. Start a command and hit tab to see your options!\n" +
	                      "\nSpecial Commands:\n" + 
	                      "  clear  (c) = clears the console output\n" +
	                      "  help   (?) = prints this help text\n" +
	                      "  reload (r) = flushes all variables and partial statements");
	          self.doPrompt()
	          break;
	        case "reload": case "r":
	          $.ajax({
	            url:      self.url + 'reload.php',
	            type:     'POST',
	            dataType: 'json',
	            data:     { reload: true },
	            success:  function(j) {
	              self.print(j.output);
	              self.doPrompt();
	            }
	          });
	          break;
	        default:
	          $.ajax({
	            url:      self.url + 'query.php',
	            type:     'POST',
	            dataType: 'json',
	            data:     { query: val, signature: hex_hmac_sha1( self.secret, val ) },
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
	              if (typeof p != "undefined") {
	                self.doPrompt(p);
	              } else {
	                self.doPrompt();
	              }
	            },
	            error:  function() {
	              self.error("Most likely syntax. Forget the semicolon? If not, try 'reload' and re-execute");
	              self.doPrompt();
	            }
	          });
	        } // end case
			});

		},

		about: function() {
		  var str = '<div id="header">' + 
		            'WordPress Console [0.2.0] by <a target="_blank" href="http://jerodsanto.net">Jerod Santo</a>' +
		            '</div>';
		  this.shell.append(str);
		},

		print: function(string) {
			this.shell.append('<div class="result"><pre>' + string + '</pre></div>');
		},

		error: function(string) {
		  this.shell.append('<div class="err">Error: ' + string + '</div>');
		},

		check: function(json) {

			// make sure json result is not an error
			if (typeof json.error != "undefined") {
				this.error(json.error);
				return false;
			} else {
				return true;
			}
		}

	}
	$(document).ready(function() { $.consoleController.init(); });
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