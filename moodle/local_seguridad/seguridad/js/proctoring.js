function init_proctoring(Y, config) {
    var urlParams = new URLSearchParams(window.location.search);
    var attemptId = urlParams.get('attempt') || config.cmid;
    var storageKey = 'seguridad_strikes_attempt_' + attemptId;
    var focusLossCount = parseInt(sessionStorage.getItem(storageKey) || '0');
    var leaveTime = 0;
    var timerDisplay = null;
    var checkInterval = null;

    function logInfraction(action, status) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", config.wwwroot + '/local/seguridad/ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('courseid=' + config.courseid + '&action=' + action + '&status=' + status + '&sesskey=' + config.sesskey);
    }

    function showModal(title, msg, bgColor, isFatal) {
        var m = document.createElement('div');
        m.style.position = 'fixed';
        m.style.top = '0'; m.style.left = '0'; m.style.width = '100%'; m.style.height = '100%';
        m.style.backgroundColor = bgColor;
        m.style.color = 'white';
        m.style.display = 'flex'; m.style.flexDirection = 'column';
        m.style.alignItems = 'center'; m.style.justifyContent = 'center';
        m.style.zIndex = '9999999';
        
        var html = '<h1 style="font-size:50px; font-weight:bold; color:white; text-align:center;">' + title + '</h1>';
        html += '<p style="font-size:26px; font-weight:bold; color:white; text-align:center; max-width:80%;">' + msg.replace(/\n/g, '<br>') + '</p>';
        
        if (!isFatal) {
            html += '<button id="btn_understood" style="margin-top:30px; padding: 15px 30px; font-size: 24px; cursor: pointer; background: white; color: black; border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">Entendido, no lo volveré a hacer</button>';
        } else {
            html += '<p style="margin-top:30px; font-size: 24px; color:#ffcc00;">Expulsando del examen...</p>';
        }
        
        m.innerHTML = html;
        document.body.appendChild(m);
        
        if (!isFatal) {
            document.getElementById('btn_understood').addEventListener('click', function() {
                document.body.removeChild(m);
            });
        }
    }

    document.addEventListener('copy', function(e) { e.preventDefault(); showModal('¡ACCIÓN BLOQUEADA!', config.str_clipboard, 'rgba(220, 53, 69, 0.95)', false); logInfraction('clipboard_attempt', 'warning'); });
    document.addEventListener('cut', function(e) { e.preventDefault(); showModal('¡ACCIÓN BLOQUEADA!', config.str_clipboard, 'rgba(220, 53, 69, 0.95)', false); logInfraction('clipboard_attempt', 'warning'); });
    document.addEventListener('paste', function(e) { e.preventDefault(); showModal('¡ACCIÓN BLOQUEADA!', config.str_clipboard, 'rgba(220, 53, 69, 0.95)', false); logInfraction('clipboard_attempt', 'warning'); });

    function forceSubmitAndKick() {
        logInfraction('tab_switch', 'annulled');
        sessionStorage.setItem(storageKey, '3'); // Bloquear al estudiante permanentemente
        showModal('EXAMEN ANULADO', config.str_annulled, 'rgba(0, 0, 0, 0.95)', true);
        
        // En lugar de hackear la BD, usamos el método nativo de Moodle para enviar y terminar el intento
        setTimeout(function() {
            if (attemptId && attemptId != config.cmid) {
                var f = document.createElement('form');
                f.method = 'post';
                f.action = config.wwwroot + '/mod/quiz/processattempt.php';
                
                var i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'attempt'; i1.value = attemptId; f.appendChild(i1);
                var i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'finishattempt'; i2.value = '1'; f.appendChild(i2);
                var i3 = document.createElement('input'); i3.type = 'hidden'; i3.name = 'timeup'; i3.value = '0'; f.appendChild(i3);
                var i4 = document.createElement('input'); i4.type = 'hidden'; i4.name = 'sesskey'; i4.value = config.sesskey; f.appendChild(i4);
                
                document.body.appendChild(f);
                f.submit();
            } else {
                // Fallback si por alguna razón no tenemos el ID del intento
                window.location.href = config.wwwroot + '/course/view.php?id=' + config.courseid;
            }
        }, 3000);
    }

    // Verificar al cargar la página si el alumno ya fue expulsado previamente
    if (focusLossCount >= 3) {
        forceSubmitAndKick();
        return;
    }

    function showOverlay() {
        if (!timerDisplay) {
            timerDisplay = document.createElement('div');
            timerDisplay.style.position = 'fixed';
            timerDisplay.style.top = '0';
            timerDisplay.style.left = '0';
            timerDisplay.style.width = '100%';
            timerDisplay.style.height = '100%';
            timerDisplay.style.backgroundColor = 'rgba(220, 53, 69, 0.95)'; 
            timerDisplay.style.color = 'white';
            timerDisplay.style.display = 'flex';
            timerDisplay.style.flexDirection = 'column';
            timerDisplay.style.alignItems = 'center';
            timerDisplay.style.justifyContent = 'center';
            timerDisplay.style.zIndex = '9999999';
            timerDisplay.innerHTML = '<h1 style="font-size:50px; font-weight:bold; color:white;">¡ADVERTENCIA DE SEGURIDAD!</h1><p style="font-size:24px; color:white;">Has perdido el foco del examen. Infracción ' + focusLossCount + ' de 2 permitidas.</p><p style="font-size:30px; font-weight:bold; color:white;">Regresa en <span id="sec_count" style="font-size:60px; color:#ffcc00;">5</span> segundos o se anulará.</p>';
            document.body.appendChild(timerDisplay);
        }
    }

    function hideOverlay() {
        if (timerDisplay) {
            document.body.removeChild(timerDisplay);
            timerDisplay = null;
        }
    }

    function handleLeave() {
        if (leaveTime > 0) return; // Ya ha perdido el foco previamente
        leaveTime = Date.now();
        focusLossCount++;
        sessionStorage.setItem(storageKey, focusLossCount);

        if (focusLossCount >= 3) {
            forceSubmitAndKick();
            return;
        }

        showOverlay();
        
        checkInterval = setInterval(function() {
            var gone = Date.now() - leaveTime;
            var remaining = 5 - Math.floor(gone / 1000);
            if (remaining < 0) remaining = 0;
            
            var span = document.getElementById('sec_count');
            if (span) span.innerText = remaining;

            if (gone >= 5000) {
                clearInterval(checkInterval);
                checkInterval = null;
                forceSubmitAndKick();
            }
        }, 500);
    }

    function handleReturn() {
        if (leaveTime > 0) {
            leaveTime = 0;
            if (checkInterval) {
                clearInterval(checkInterval);
                checkInterval = null;
                hideOverlay();
                
                logInfraction('tab_switch', 'warning');
                showModal('¡PÉRDIDA DE FOCO DETECTADA!', "Has salido del examen.\n\nLlevas " + focusLossCount + " infracción(es).\nA la tercera infracción tu examen será anulado inmediatamente.", 'rgba(255, 153, 0, 0.95)', false);
            }
        }
    }

    window.addEventListener('blur', handleLeave);
    window.addEventListener('focus', handleReturn);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) handleLeave();
        else handleReturn();
    });
}
