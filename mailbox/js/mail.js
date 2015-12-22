jQuery(document).ready(function ()
{
    var id;
    var count;
    jQuery('body').delegate('.email_from', 'click', function () {
        id = $(this).parent().parent().find('.mailbox-index').val();
        count = $('.total_message').text().split(' ');
        $('.inbox_form').attr('action', '?r=MailInbox&mail_id=' + id + "&number=" + count[0]);
        $('.inbox_form').submit();
    });
    jQuery(".mailbox-read-message").html(jQuery(".mailbox-read-message").text());
    jQuery('.inbox ,.btn-default').on('click', function () {
        window.location.reload();
    });
    $('.inbox_link').on('click',function(){ 
        window.location.href="?r=MailInbox";
    });
});
