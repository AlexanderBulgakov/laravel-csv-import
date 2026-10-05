require('./bootstrap');

function updateProgress(url, element) {
    axios.get(url)
        .then(function (response) {
            const progress = response.data.progress;
            const finished = response.data.finished;
            const status = response.data.status;
            const total_rows = response.data.total_rows;
            const processed_rows = response.data.processed_rows;
            const failed_rows = response.data.failed_rows;
            const error_message = response.data.error_message;

            element.querySelector('.progress-bar').style.width = `${progress}%`;
            element.querySelector('.progress-value').textContent = `${progress}%`;

            element.querySelector('.js-status').textContent = status;
            element.querySelector('.js-total_rows').textContent = total_rows;
            element.querySelector('.js-processed_rows').textContent = processed_rows;
            element.querySelector('.js-failed_rows').textContent = failed_rows;
            element.querySelector('.js-error_message').textContent = error_message;

            if (!finished) {
                setTimeout(() => {
                    updateProgress(url, element);
                }, 2000);
            }
        })
        .catch(function (error) {
            console.error('Error:', error);

            setTimeout(() => {
                updateProgress(url, element);
            }, 4000);
        });
}

document.querySelectorAll('.js-progress').forEach(function (element) {
    if (element.dataset.finished === '1') {
        return;
    }

    updateProgress(
        element.dataset.url,
        element
    );
});
