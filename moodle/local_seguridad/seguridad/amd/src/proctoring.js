define(['jquery'], function($) {
    return {
        init: function(config) {
            var warnings = 0;
            var maxWarnings = 2;

            function logInfraction(action, status) {
                $.post(M.cfg.wwwroot + '/local/seguridad/ajax.php', {
                    courseid: config.courseid,
                    action: action,
                    status: status,
                    sesskey: M.cfg.sesskey
                });
            }

            if (config.clipboard == 1) {
                $(document).on('copy paste cut', function(e) {
                    e.preventDefault();
                    alert(config.str_clipboard);
                    logInfraction('clipboard_attempt', 'warning');
                });
            }

            if (config.tabs == 1) {
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        warnings++;
                        if (warnings >= maxWarnings) {
                            alert(config.str_annulled);
                            logInfraction('tab_switch', 'annulled');
                            // Force submit logic if it's a quiz
                            if ($('form#responseform').length) {
                                $('form#responseform').submit();
                            } else if ($('input[name="finishattempt"]').length) {
                                $('input[name="finishattempt"]').click();
                            } else {
                                window.location.href = M.cfg.wwwroot + '/course/view.php?id=' + config.courseid;
                            }
                        } else {
                            alert(config.str_warning);
                            logInfraction('tab_switch', 'warning');
                        }
                    }
                });
            }
        }
    };
});
