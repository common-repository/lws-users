jQuery(function(a){a(".lws-pwd-retry-button").click(function(){location.reload();return false});function b(e,f){var c=e.closest(".lws-pwd-frame");var d=e.find("input[type='submit']");d.addClass("lws-waiting-button").prop("disabled",true);a.post(ajaxurl,f,function(g){c.children("div").hide();if(0!=g){var h=c.find(g.ok?".lws-pwd-success":".lws-pwd-error");h.show().find(".lws-return-description").html(g.html)}else{c.find(".lws-pwd-error").show().find(".lws-return-description").html("A problem occured on the server, please retry later.")}},"json").fail(function(h,i,g){c.children("div").hide();c.find(".lws-pwd-error").show().find(".lws-return-description").html("Connection problem:<br/>"+i+", "+g)}).done(function(){d.removeClass("lws-waiting-button").prop("disabled",false)})}a("form[name='lostpwdform']").submit(function(c){console.log("coucou");b(a(this),{action:"lws_users_lost_pwd",nonce:a(this).find("input[name='nonce']").val(),email:a(this).find("input[name='email']").val()});c.preventDefault();return false});a("form[name='changepwdform']").submit(function(c){b(a(this),{action:"lws_users_change_pwd",nonce:a(this).find("input[name='nonce']").val(),pwd:a(this).find("input[name='pwd']").val(),dup:a(this).find("input[name='pwd2']").val()});c.preventDefault();return false})});