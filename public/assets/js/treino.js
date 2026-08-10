function startTimer() {
    let minutes = document.getElementById("minutes").value;
    let seconds = minutes * 60;

    const alarm = new Audio('../assets/audio/alarm1.mp3');

    let countdown = setInterval(() => {
        let min = Math.floor(seconds / 60);
        let sec = seconds % 60;
        document.getElementById("timer").textContent =
            `${min}:${sec.toString().padStart(2, "0")}`;
        
        if (seconds <= 0) {
            clearInterval(countdown);
            alarm.play();
        }
        seconds--;
    }, 1000);
}

const value = document.getElementById('value');
const minusButton = document.getElementById('minus');
const plusButton = document.getElementById('plus');
const resetButton = document.getElementById('reset');

const updateValue = () => {
    value.innerHTML = count;
};

let count = 0;
let intervalid = 0;

plusButton.addEventListener('mousedown', () => {
    intervalid = setInterval(() => {
        count += 1;
        updateValue();
    }, 100);
});

minusButton.addEventListener('mousedown', () => {
    intervalid = setInterval(() => {
        count -= 1;
        updateValue();
    }, 100);
});

resetButton.addEventListener('click', () => {
    count = 0;
    updateValue();
});

document.addEventListener('mouseup', () => clearInterval(intervalid));
