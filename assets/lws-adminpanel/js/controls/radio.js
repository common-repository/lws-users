(function(a){a.widget("lws.lws_radio",{options:{baseIcon:"lws-icon-star-empty",baseColor:"#666",selectIcon:"lws-icon-star-full",selectColor:"#e6a903",size:"20px",optClass:""},_create:function(){this._getDataOptions();this._createStructure();this.setState();this.container.on("click",".lws-radio-button",this._bind(this._clickRadio,this));this.container.on("change",".lws_radio",this._bind(this._test,this))},_bind:function(b,c){return function(){return b.apply(c,arguments)}},_getDataOptions:function(){if(this.element.data("baseicon")!=undefined){this.options.baseIcon=this.element.data("baseicon")}if(this.element.data("optclass")!=undefined){this.options.optClass=this.element.data("optclass")}if(this.element.data("basecolor")!=undefined){this.options.baseColor=this.element.data("basecolor")}if(this.element.data("selecticon")!=undefined){this.options.selectIcon=this.element.data("selecticon")}if(this.element.data("selectcolor")!=undefined){this.options.selectColor=this.element.data("selectcolor")}if(this.element.data("size")!=undefined){this.options.size=this.element.data("size")}},_createStructure:function(){this.container=this.element.parent();a("<div>",{"class":"lws-radio-wrapper "+this.options.optClass}).append(a("<div>",{"class":"lws-radio-button "+this.options.baseIcon,css:{height:this.options.size,color:this.options.baseColor}})).appendTo(this.container);this.element.css("display","none").detach().prependTo(this.container.find(".lws-radio-wrapper"));this.button=this.container.find(".lws-radio-button");this.name=this.element.attr("name")},setState:function(){if(this.element.prop("checked")){this.button.removeClass(this.options.baseIcon).addClass(this.options.selectIcon);this.button.css("color",this.options.selectColor)}else{this.button.removeClass(this.options.selectIcon).addClass(this.options.baseIcon);this.button.css("color",this.options.baseColor)}},_clickRadio:function(b){if(!this.element.prop("checked")){this.element.prop("checked",true);a(".lws_radio[name="+this.name+"]").lws_radio("setState")}}})})(jQuery);jQuery(function(a){a(".lws_radio").lws_radio()});