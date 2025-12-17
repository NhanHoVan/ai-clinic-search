jQuery(document).ready(function($) {
    $('.upload_image_button').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var file_frame = wp.media({
            title: 'Chọn logo cho màn hình đăng nhập',
            library: { type: 'image' },
            multiple: false
        }).open()
        .on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            button.prev('input').val(attachment.url);
        });
    });
});
