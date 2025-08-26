window.onload = function () {
    if (window.location.href.indexOf("?enviado=true") > -1) {
        calcula();
    }
};

function calcula() {
    var peso = parseFloat(document.getElementById('peso').value);
    var tempo = parseFloat(document.getElementById('tempo').value);
    var velocidade = parseFloat(document.getElementById('velocidade').value);

    if (isNaN(peso) || isNaN(tempo) || isNaN(velocidade)) {
        alert("Por favor, preencha todos os campos corretamente.");
        return false;
    }
    
    var gasto = velocidade * peso * 0.0175 * tempo;
    
    var notification = document.getElementById("notification");
    notification.innerHTML = "Você gastou " + gasto.toFixed(2) + " calorias";
    notification.className = "notification show";
    setTimeout(function () {
        notification.className = "notification";
    }, 3000);

    return false;
}

//mostrar formulário
document.addEventListener('DOMContentLoaded', function () {
    const addButton = document.querySelector('.addbutton');
    const form = document.getElementById('formulario');

    addButton.addEventListener('click', function () {
        // Verifica se o formulário está visível
        if (form.style.display === 'block') {
            form.style.display = 'none';
        } else {
            form.style.display = 'block';
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const dataInput = document.getElementById('data_atividade');
    const formulario = document.getElementById('formulario');

    // Formatação no input
    dataInput.addEventListener('input', function (e) {
        let value = e.target.value;
        value = value.replace(/\D/g, '');

        if (value.length > 2 && value.length <= 4) {
            value = `${value.slice(0, 2)}/${value.slice(2)}`;
        } else if (value.length > 4) {
            value = `${value.slice(0, 2)}/${value.slice(2, 4)}/${value.slice(4, 8)}`;
        }

        e.target.value = value;
    });

    // Converter data para formato YYYY-MM-DD antes do envio
    formulario.addEventListener('submit', function (e) {
        const dataField = document.getElementById('data_atividade');
        const dataValue = dataField.value;
        const parts = dataValue.split('/');

        if (parts.length === 3) {
            const formattedDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
            dataField.value = formattedDate;
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const horaInput = document.getElementById('hora_atividade');
    const formulario = document.getElementById('formulario');

    // Função para formatar a hora com dois pontos
    function formatarHora(hora) {
        // Remove qualquer caractere que não seja número
        hora = hora.replace(/\D/g, '');

        // Adiciona dois pontos após os dois primeiros dígitos
        if (hora.length > 2) {
            hora = hora.slice(0, 2) + ':' + hora.slice(2, 4);
        }

        return hora;
    }

    // Formatação no input
    horaInput.addEventListener('input', function (e) {
        e.target.value = formatarHora(e.target.value);
    });

    // Converter hora para formato 24 horas antes do envio
    formulario.addEventListener('submit', function (e) {
        const horaField = document.getElementById('hora_atividade');
        const horaValue = horaField.value;

        const timeParts = horaValue.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?/i);

        if (timeParts) {
            let [_, hours, minutes, period] = timeParts;
            hours = parseInt(hours, 10);
            minutes = parseInt(minutes, 10);

            if (period) {
                period = period.toUpperCase();
                if (period === 'PM' && hours !== 12) {
                    hours += 12;
                } else if (period === 'AM' && hours === 12) {
                    hours = 0;
                }
            } else if (hours === 12) {
                hours = 0; // Midnight case
            }

            const formattedTime = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
            horaField.value = formattedTime;
        }
    });
});

function togglePesoInput() {
    var checkBox = document.getElementById("checkPeso");
    var pesoField = document.getElementById("pesoField");

    if (checkBox.checked) {
        pesoField.style.display = "block";
    } else {
        pesoField.style.display = "none";
    }
}

// Mostrar/ocultar campos com base no esporte selecionado
document.addEventListener("DOMContentLoaded", function () {
  const esporteSelect = document.querySelector("select[name='EsporteAtividade']")
  const fieldDistancia = document.getElementById("field-distancia")
  const fieldDuracao = document.getElementById("field-duracao")
  const fieldElevacao = document.getElementById("field-elevacao")

  function show(el, on) { if (!el) return; el.style.display = on ? "" : "none" }

  function atualizarCampos() {
    const v = esporteSelect ? esporteSelect.value : ""
    show(fieldDistancia, false)
    show(fieldDuracao, false)
    show(fieldElevacao, false)

    if (["Caminhada","Corrida","Marcha Atlética","Trilha","Ciclismo","Mountain Bike","Downhill","BMX"].includes(v)) {
      show(fieldDistancia, true)
      show(fieldDuracao, true)
      show(fieldElevacao, true)
    } else if (["Nado de peito","Nado de costas","Nado borboleta"].includes(v)) {
      show(fieldDistancia, true)
      show(fieldDuracao, true)
    } else if (["Tênis","Tênis de mesa","Badminton","Padel","Beach Tennis","Arremesso de peso","Lançamento de disco","Lançamento de dardo","Lançamento de martelo"].includes(v)) {
      show(fieldDuracao, true)
    }
  }

  if (esporteSelect) {
    esporteSelect.addEventListener("change", atualizarCampos)
    atualizarCampos()
  }
})
