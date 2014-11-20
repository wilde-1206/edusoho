define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');
    var Uploader = require('upload');

    exports.run = function() {
        var $table=$('.eduMaterial-table');
        $table.popover({
            selector: '.material-selector',
            trigger: 'click',
            placement: 'bottom',
            html: true,
            delay: 0,
            viewport: { selector: '.eduMaterial-table', padding: 0 },
            content: function() {
                return $(this).find('.material-list').html();
            }
        });

        $table.on('click','.material-name',function(){
            $(this).closest('.materialTd').find('.eduMaterial-name').html($(this).html());
            $table.find('.popover').hide();
            $.post(
                $(this).data('url'),
                { eduMaterialId:$(this).data('edumaterialid'),
                  materialId:$(this).data('materialid')
                },
                function(data){
                    if(data){
                        Notify.success('修改教材成功');
                    }else{
                        Notify.danger('修改教材失败');
                    }
                }
            );
        });

    }
});