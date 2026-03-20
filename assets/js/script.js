/**
 * Auto Finance Online Calculator Logic
 * Includes robust initialization and aggressive diagnostic logging to trace execution state.
 */

// Global log to confirm the file is physically loaded by the browser
console.log('[AFO] script.js has successfully loaded into the browser.');

/**
 * Core initialization function for the AFO Calculator.
 * Extracts DOM data, binds event listeners, and triggers the initial API payload.
 * * @return {void}
 */
function initializeAfoCalculator() {
    console.log('[AFO] Initialization function triggered.');

    const container = document.querySelector('.afo-calculator-container');
    
    if (!container) {
        console.error('[AFO] FATAL: .afo-calculator-container not found in the DOM. The shortcode is not rendering properly or the selector is wrong.');
        return;
    }

    console.log('[AFO] Container found. Extracting dataset...', container.dataset);

    const afoConfig = {
        apiKey: container.dataset.apiKey,
        apiUrl: container.dataset.apiUrl,
        price: parseFloat(container.dataset.price)
    };

    if (!afoConfig.apiKey || isNaN(afoConfig.price)) {
        console.error('[AFO] FATAL: Configuration data is missing or invalid.', afoConfig);
        return;
    }

    const price = afoConfig.price;
    const depositSlider = document.getElementById('afo-deposit');
    const borrowSlider = document.getElementById('afo-borrow');
    const termSlider = document.getElementById('afo-term');
    const quoteBtn = document.getElementById('afo-quote-btn');

    if (!depositSlider || !borrowSlider || !termSlider) {
        console.error('[AFO] FATAL: One or more slider inputs are missing from the DOM.');
        return;
    }

    let debounceTimer;

    /**
     * Synchronizes the Deposit and Borrow sliders based on the fixed vehicle price.
     * * @param {Event} e The input event object triggered by slider manipulation.
     * @return {void}
     */
    function syncSliders(e) {
        if (e && e.target) {
             console.log(`[AFO] Slider manipulated: ${e.target.id} set to ${e.target.value}`);
        }

        if (e && e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        } else if (e && e.target.id === 'afo-borrow') {
            depositSlider.value = price - parseFloat(borrowSlider.value);
        }

        document.getElementById('afo-display-deposit').innerText = `£${parseFloat(depositSlider.value).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('afo-display-borrow').innerText = `£${parseFloat(borrowSlider.value).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('afo-display-term').innerText = `${termSlider.value} months`;
        document.getElementById('afo-res-months').innerText = termSlider.value;

        triggerApiCall();
    }

    /**
     * Executes the API call using a debounce mechanism to optimize performance.
     * * @return {void}
     */
    function triggerApiCall() {
        console.log('[AFO] Preparing API call...');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchFinanceData(depositSlider.value, termSlider.value);
        }, 500); 
    }

    /**
     * Fetches quote details from the Auto Finance Online API.
     * * @param {number|string} deposit The current deposit amount.
     * @param {number|string} term The repayment term in months.
     * @return {Promise<void>}
     */
    async function fetchFinanceData(deposit, term) {
        console.log(`[AFO] Executing API Request -> Deposit: ${deposit}, Term: ${term}`);
        
        const baseUrl = afoConfig.apiUrl;
        const params = new URLSearchParams({
            vehicle_price: price,
            deposit: parseFloat(deposit),
            term_length: parseInt(term, 10)
        });

        try {
            const requestUrl = `${baseUrl}?${params.toString()}`;
            console.log(`[AFO] Fetching: ${requestUrl}`);

            const response = await fetch(requestUrl, {
                method: 'GET',
                headers: {
                    'X-API-Key': afoConfig.apiKey,
                    'Content-Type': 'application/json'
                }
            });

            console.log(`[AFO] API HTTP Status: ${response.status}`);

            if (response.status === 429) {
                const retryAfter = response.headers.get("Retry-After") || 60;
                console.warn(`[AFO] Rate limit hit. Retrying in ${retryAfter}s.`);
                setTimeout(() => fetchFinanceData(deposit, term), retryAfter * 1000);
                return;
            }

            if (!response.ok) {
                let errorMsg = `HTTP ${response.status} ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMsg = errorData.message;
                } catch (e) {}
                throw new Error(`API Rejected: ${errorMsg}`);
            }

            const result = await response.json();
            console.log('[AFO] Successful API Response received:', result);

            if (result.success && result.data && result.data.finance_options) {
                const bestOption = result.data.finance_options.find(opt => opt.type === 'excellent');

                if (bestOption) {
                    document.getElementById('afo-res-rate').innerText = `${bestOption.apr}% APR`;
                    document.getElementById('afo-res-credit').innerText = `£${parseFloat(bestOption.cost_of_credit).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('afo-res-total').innerText = `£${parseFloat(bestOption.total_repayable).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('afo-res-monthly').innerText = `£${parseFloat(bestOption.monthly_cost).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    console.log('[AFO] UI successfully updated with new calculation.');
                }

                if (result.data.referrer && result.data.referrer.link) {
                    quoteBtn.onclick = () => {
                        window.open(result.data.referrer.link, '_blank');
                    };
                }
            } else {
                console.warn('[AFO] Unrecognized API response structure.', result);
            }

        } catch (error) {
            console.error('[AFO] Network/Transport Error:', error);
        }
    }

    depositSlider.addEventListener('input', syncSliders);
    borrowSlider.addEventListener('input', syncSliders);
    termSlider.addEventListener('input', syncSliders);

    console.log('[AFO] Event listeners bound. Forcing initial sync...');
    syncSliders({ target: null }); 
}

// Bulletproof execution: Check if DOM is already loaded. If so, run immediately. If not, wait for it.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAfoCalculator);
} else {
    initializeAfoCalculator();
}