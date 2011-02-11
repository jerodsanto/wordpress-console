(function($){
  $.wpConsole = function() {
    var self = this;

    self.queries      = [];
    self.counter      = 0;
    self.historyIndex = 0;

    self.init = function() {
      self.version = WP_CONSOLE_VERSION;
      self.url     = WP_CONSOLE_URL;
      self.secret  = WP_CONSOLE_SECRET;
      self.root    = WP_ROOT_PATH;

      // create shell div
      self.shell = $("<div id='shell'></div>").appendTo($("#wrapper"));

      // listen for clicks on the shell
      self.shell.click(function() {
        self.shell.find("input.current").focus();
      });

      // listen for key presses (up, down, tab and ctrl+l)
      $(document).keyup(function(e) {
        var key   = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
        var input = self.shell.find("input.current:last");

        switch (key) {
          case 38: // up
            var lastQuery = self.queries[self.historyIndex];
            if (typeof lastQuery != "undefined") {
              self.historyIndex--;
              input.val(lastQuery).focus();
            }
            // no negative history allowed
            if (self.historyIndex < 0) {
              self.historyIndex = 0;
            }
            break;
          case 40: // down
            var nextQuery = self.queries[self.historyIndex+1];
            if (typeof nextQuery != "undefined") {
              self.historyIndex++;
              input.val(nextQuery).focus();
            } else {
              // put it at the end
              self.historyIndex = self.queries.length - 1;
              input.val("");
            }
            break;
          case 76: // l
            if (e.ctrlKey) {
              self.clear();
            }
            break;
          case 65: // a
            if (e.ctrlKey) {
              input.setCursorPosition(0);
            }
            break;
          case 69: // e
          if (e.ctrlKey) {
            input.setCursorPosition(input.val().length);
          }
        } // switch
      });

      $(document).keydown(function(e) {
        var key   = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
        var input = self.shell.find("input.current:last");

        switch (key) {
          case 38:
          case 40:
            e.preventDefault();
            break;
          case 9: // tab
            var lastval = input.val();
            e.preventDefault(); // don't do browser default action

            self.postJSON("complete", lastval, {
              success:  function(j) {
                if (self.check(j)) {
                  if (j.length == 0) {
                    return;
                  } else if (j.length == 1) {
                    input.val(j).focus();
                  } else {
                    // print 3-column listing of array values
                    buffer_to_longest(j);
                    while (j.length > 0) {
                      var line = j.splice(0,3);
                      self.print(line.join(" "));
                    }
                    self.doPrompt();
                    self.shell.find("input.current:last").val(lastval);
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

    self.doPrompt = function(prompt) {
      self.counter++;

      // default prompt to >> unless passed in as argument
      prompt = (prompt) ? prompt : ">>";

      // append prompt to shell
      var $row      = $("<div class='row' id='" + self.counter + "'></div>");
      var $prompt   = $("<span class='prompt'>" + prompt + "</span>");
      var $form     = $("<form></form>");
      var $input    = $("<input class='current' type='text' />");

      $form.append( $input );
      $row.append( $prompt ).append( $form );

      self.shell.append( $row );

      // set width and focus input
      var input_width = self.shell.width() - 50;
      $input.width( input_width ).focus();

      // listen for submit
      $form.submit(function(e) {
        var val = $input.val();

        e.preventDefault();

        $input.removeClass("current");

        // save in history and handle accordingly
        self.historyIndex = self.queries.length;
        self.queries[self.historyIndex] = val;

        switch(val) {
          case "clear": case "c":
            self.clear();
            break;
          case "help": case "?":
            self.print("\nWhat's New:\n" +
            "  Keyboard shortcuts: ctrl+l = clear, ctrl+a = goto start, ctrl+e = goto end\n" +
            "  Admin functions: add_user(), wp_delete_user(), and friends!\n" +
            "\n" +
            "Special Commands:\n" +
            "  clear  (c) = clears the console output\n" +
            "  help   (?) = prints this help text\n" +
            "  reload (r) = flushes all variables and partial statements\n" +
            "\n" +
            "Why are all my objects of type stdClass?\n" +
            "  Your class needs to implement the __set_state() static method to\n" +
            "  be restored properly. You should Google it.");
            self.doPrompt();
            break;
          case "reload": case "r":
            self.reload(true);
            break;
          default:
            self.postJSON("query", val, {
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
                      self.print("");
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
        } // end switch
      }); // end form.submit
    }

    self.clear = function() {
      self.$header.siblings().empty();
      self.counter = 0;
      self.doPrompt();
    }

    self.reload = function(show_message) {
      var callbacks = {};
      if (show_message === true) {
        callbacks.success = function(j) {
          self.print(j.output);
          self.doPrompt();
        }
      }
      return self.postJSON("reload", callbacks);
    }

    self.postJSON = function(page, value, callbacks, additional_data) {
      var request = {
        type:     "POST",
        dataType: "json",
        data: {
          "root": self.root
        }
      };
      var key = null;

      if (typeof(value) == "object") {
        additional_data = callbacks;
        callbacks       = value;
      }

      if (callbacks)       request = $.extend(request, callbacks);
      if (additional_data) request = $.extend(request, additional_data);

      switch(page) {
        case "query":
          key = "query";
          break;
        case "complete":
          key = "partial";
          break;
      }

      request.url = self.url + page + ".php";

      if (key) {
        request.data[key]      = value;
        request.data.signature = hex_hmac_sha1( self.secret, value );
      } else {
        request.data.reload = true;
      }

      return $.ajax(request);
    }

    self.about = function() {
      self.$header  = $("<div id='header'>" +
      "WordPress Console [" + self.version + "] by " +
      "<a target='_blank' href='http://jerodsanto.net'>Jerod Santo</a>" +
      "</div>");
      self.shell.append(self.$header);
    }

    self.print = function(string) {
      // Using text() escapes HTML to output visible tags
      var result = $("<pre></pre>").text(string);
      self.shell.append( $("<div class='result'></div>").append(result) );
    }

    self.error = function(string) {
      self.shell.append("<div class='err'>Error: " + string + "</div>");
    }

    self.check = function(json) {
      // make sure json result is not an error
      if (typeof json.error != "undefined") {
        self.error(json.error);
        return false;
      } else {
        return true;
      }
    }

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
        string += " ";
      }
      return string;
    }

    self.init();
  }

  // thanks to: http://stackoverflow.com/questions/499126/jquery-set-cursor-position-in-text-area
  $.fn.setCursorPosition = function(pos) {
    var $self = $(this);
    if ($self.get(0).setSelectionRange) {
      $self.get(0).setSelectionRange(pos, pos);
    } else if ($self.get(0).createTextRange) {
      var range = $self.get(0).createTextRange();
      range.collapse(true);
      range.moveEnd("character", pos);
      range.moveStart("character", pos);
      range.select();
    }
  }

  $(function() {
    window.wpConsole = new $.wpConsole();
  });
})(jQuery);
