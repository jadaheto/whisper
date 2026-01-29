const CHUNK_SIZE = 20 * 1024 * 1024; // 20MB chunks

const dropZone = document.getElementById('dropZone');
const audioFile = document.getElementById('audioFile');
const fileLabel = document.getElementById('fileLabel');
const form = document.getElementById('transcriptionForm');
const progressContainer = document.getElementById('progressContainer');
const progressBar = document.getElementById('progressBar');
const progressPercent = document.getElementById('progressPercent');
const statusText = document.getElementById('statusText');
const resultContainer = document.getElementById('resultContainer');
const transcriptionText = document.getElementById('transcriptionText');
const errorContainer = document.getElementById('errorContainer');
const errorText = document.getElementById('errorText');
const submitBtn = document.getElementById('submitBtn');

// UI Interactions
dropZone.addEventListener('click', () => audioFile.click());
audioFile.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        fileLabel.innerText = e.target.files[0].name;
    }
});

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = audioFile.files[0];
    const apiKey = document.getElementById('apiKey').value;
    const email = document.getElementById('email').value;

    if (!file) {
        showError("Por favor selecciona un archivo de audio.");
        return;
    }

    resetUI();
    submitBtn.disabled = true;
    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');

    try {
        const fileId = Date.now().toString(36) + Math.random().toString(36).substr(2);
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        statusText.innerText = "Subiendo archivo...";
        progressContainer.classList.remove('hidden');

        // Chunked Upload
        for (let i = 0; i < totalChunks; i++) {
            const start = i * CHUNK_SIZE;
            const end = Math.min(file.size, start + CHUNK_SIZE);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append('chunk', chunk);
            formData.append('fileName', file.name);
            formData.append('chunkIndex', i);
            formData.append('totalChunks', totalChunks);
            formData.append('fileId', fileId);

            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            }).catch(fetchErr => {
                console.error("Fetch Error details:", fetchErr);
                throw new Error("No se pudo conectar con el servidor (Failed to fetch). Asegúrate de estar usando un servidor local como XAMPP y no abriendo el archivo .html directamente.");
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error("Server Response Error:", errorText);
                throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
            }

            const result = await response.json();
            if (result.error) throw new Error(result.error);

            const progress = Math.round(((i + 1) / totalChunks) * 100);
            updateProgress(progress, `Subiendo fragmento ${i + 1} de ${totalChunks}...`);
        }

        // Transcription Phase
        updateProgress(100, "Procesando transcripción. Puedes cerrar esta ventana, te enviaremos el resultado por correo.");
        statusText.classList.add('animate-pulse');

        const transData = new FormData();
        transData.append('apiKey', apiKey);
        transData.append('email', email);
        transData.append('fileName', fileId + '_' + file.name);

        const transcribeResponse = await fetch('api/transcribe.php', {
            method: 'POST',
            body: transData
        }).catch(fetchErr => {
            console.error("Transcription Fetch Error:", fetchErr);
            throw new Error("Fallo en la conexión durante la transcripción.");
        });

        if (!transcribeResponse.ok) {
            const errorText = await transcribeResponse.text();
            console.error("Transcription Server Error:", errorText);
            throw new Error(`Error en transcripción: ${transcribeResponse.status}`);
        }

        const transResult = await transcribeResponse.json();

        if (transResult.error) {
            let errorMsg = transResult.error;
            if (transResult.details) {
                errorMsg += "\n\nDetalles de FFmpeg:\n" + transResult.details.join("\n");
                errorMsg += "\n\nComando ejecutado:\n" + transResult.command;
            }
            if (transResult.debug_path) {
                errorMsg += "\n\nRuta de FFmpeg intentada: " + transResult.debug_path;
            }
            throw new Error(errorMsg);
        }

        showResult(transResult.transcription);
    } catch (err) {
        showError(err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        statusText.classList.remove('animate-pulse');
    }
});

function resetUI() {
    errorContainer.classList.add('hidden');
    resultContainer.classList.add('hidden');
    progressContainer.classList.add('hidden');
    updateProgress(0, "");
}

function updateProgress(percent, text) {
    progressBar.style.width = percent + '%';
    progressPercent.innerText = percent + '%';
    if (text) statusText.innerText = text;
}

function showResult(text) {
    resultContainer.classList.remove('hidden');
    transcriptionText.innerText = text;
    statusText.innerText = "Transcripción completada";
}

function showError(msg) {
    errorContainer.classList.remove('hidden');
    errorText.innerText = msg;
    progressContainer.classList.add('hidden');
}

function copyTranscription() {
    const text = transcriptionText.innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert("¡Transcripción copiada!");
    });
}
