!function(t){var a={};function n(e){if(a[e])return a[e].exports;var r=a[e]={i:e,l:!1,exports:{}};return t[e].call(r.exports,r,r.exports,n),r.l=!0,r.exports}n.m=t,n.c=a,n.d=function(e,r,t){n.o(e,r)||Object.defineProperty(e,r,{enumerable:!0,get:t})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(r,e){if(1&e&&(r=n(r)),8&e)return r;if(4&e&&"object"==typeof r&&r&&r.__esModule)return r;var t=Object.create(null);if(n.r(t),Object.defineProperty(t,"default",{enumerable:!0,value:r}),2&e&&"string"!=typeof r)for(var a in r)n.d(t,a,function(e){return r[e]}.bind(null,a));return t},n.n=function(e){var r=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(r,"a",r),r},n.o=function(e,r){return Object.prototype.hasOwnProperty.call(e,r)},n.p="/static-dist/",n(n.s=617)}({617:function(e,r){var t=$("#share-materials-form"),a=$("#target-teachers-input"),n=$("#target-teachers-data").data("value");a.select2({multiple:!0,data:n}),a.on("change",function(e){$(".jq-validate-error").hide(),$(".has-error").removeClass("has-error")}),t.validate({ajax:!0,currentDom:"#form-submit",rules:{targetUserIds:{required:!0,visible_character:!0}},messages:{targetUserIds:{required:Translator.trans("material.share.teacher_nickname_label")}},submitSuccess:function(){t.closest(".modal").modal("hide")}})}});