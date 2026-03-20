/**
 * Auto Finance Online Calculator Logic — Redesigned UI
 * Handles slider synchronisation, bubble labels, arrow step buttons,
 * UI formatting and external API queries with debouncing.
 */

let afoInitAttempts = 0;

/**
 * Core initialisation sequence for the calculator logic.
 * Enforces strict dependency checking for the globally localised afoConfig object.
 *
 * @return {void}
 */
function initializeAfoCalculator() {

    // Polling mechanism: Wait for afoConfig if async/defer optimisations altered script execution order
    if (typeof afoConfig === 'undefined') {
        afoInitAttempts++;
        if (afoInitAttempts < 40) { // Retry for up to 2 seconds
            setTimeout(initializeAfoCalculator, 50);
        } else {
            console.error('AFO Calculator FATAL: afoConfig object never initialized.');
        }
        return;
    }

    const price        = parseFloat(afoConfig.price);
    const depositSlider = document.getElementById('afo-deposit');
    const borrowSlider  = document.getElementById('afo-borrow');
    const termSlider    = document.getElementById('afo-term');

    // Graceful exit if shortcode markup is absent from the current DOM
    if (!depositSlider || !borrowSlider || !termSlider) return;

    let debounceTimer;

    // ─── Bubble helpers ────────────────────────────────────────────────────────

    /**
     * Calculates the pixel offset for a slider's floating bubble so that it
     * tracks the thumb centre across the full slider width.
     *
     * @param {HTMLInputElement} slider  The range input element.
     * @param {HTMLElement}      bubble  The tooltip element to position.
     * @param {Function}         formatFn  Returns the display string for a given value.
     * @return {void}
     */
    function updateBubble(slider, bubble, formatFn) {
        if (!slider || !bubble) return;

        const val = parseFloat(slider.value);
        const min = parseFloat(slider.min);
        const max = parseFloat(slider.max);
        const pct = (max === min) ? 0 : (val - min) / (max - min);

        // Thumb radius ~11 px (24 px diameter / 2 + 1 px visual fudge)
        const thumbRadius = 11;
        const trackWidth  = slider.offsetWidth;
        const offset      = thumbRadius + pct * (trackWidth - thumbRadius * 2);

        bubble.style.left    = offset + 'px';
        bubble.textContent   = formatFn(val);
    }

    /**
     * Formats a GBP monetary value with two decimal places.
     * @param {number} val
     * @return {string}
     */
    function fmtGBP(val) {
        return '£' + parseFloat(val).toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Formats a year value with singular/plural suffix.
     * @param {number} val
     * @return {string}
     */
    function fmtYears(val) {
        const y = parseFloat(val);
        return y + ' year' + (y === 1 ? '' : 's');
    }

    // Cache bubble elements
    const bubbleDeposit = document.getElementById('afo-bubble-deposit');
    const bubbleBorrow  = document.getElementById('afo-bubble-borrow');
    const bubbleTerm    = document.getElementById('afo-bubble-term');

    // ─── Arrow step buttons ────────────────────────────────────────────────────

    /**
     * Wires all `.afo-arrow-btn` elements to increment or decrement the target
     * slider by one step in the given direction.
     */
    document.querySelectorAll('.afo-arrow-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const dir      = parseInt(this.dataset.dir, 10);
            const slider   = document.getElementById(targetId);
            if (!slider) return;

            const step   = parseFloat(slider.step) || 1;
            const newVal = Math.min(
                Math.max(parseFloat(slider.value) + dir * step, parseFloat(slider.min)),
                parseFloat(slider.max)
            );

            slider.value = newVal;
            slider.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // ─── Slider synchronisation ────────────────────────────────────────────────

    /**
     * Synchronises the Deposit and Borrow sliders based on the fixed vehicle
     * price, updates all display text, repositions all bubbles, and queues an
     * API call via the debounce buffer.
     *
     * @param {Event|Object} e The input event or synthetic trigger object.
     * @return {void}
     */
    function syncSliders(e) {
        if (e && e.target && e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        } else if (e && e.target && e.target.id === 'afo-borrow') {
            depositSlider.value = price - parseFloat(borrowSlider.value);
        }

        const currentBorrow = parseFloat(borrowSlider.value);
        const exactDeposit  = price - currentBorrow;
        const termYears     = parseFloat(termSlider.value);
        const termMonths    = Math.round(termYears * 12);

        // ── Price header display ──────────────────────────────────────────────
        const displayDeposit = document.getElementById('afo-display-deposit');
        const displayBorrow  = document.getElementById('afo-display-borrow');
        if (displayDeposit) displayDeposit.innerText = fmtGBP(exactDeposit);
        if (displayBorrow)  displayBorrow.innerText  = fmtGBP(currentBorrow);

        // ── Term display ──────────────────────────────────────────────────────
        const displayTerm = document.getElementById('afo-display-term');
        if (displayTerm) displayTerm.innerText = fmtYears(termYears);

        const resMonths = document.getElementById('afo-res-months');
        if (resMonths) resMonths.innerText = termMonths;

        // ── Hero info panel: live borrowing value ─────────────────────────────
        const infoBorrow = document.getElementById('afo-info-borrow');
        if (infoBorrow) infoBorrow.innerText = fmtGBP(currentBorrow);

        // ── Reposition all bubbles ────────────────────────────────────────────
        updateBubble(depositSlider, bubbleDeposit, fmtGBP);
        updateBubble(borrowSlider,  bubbleBorrow,  fmtGBP);
        updateBubble(termSlider,    bubbleTerm,    fmtYears);

        triggerApiCall(exactDeposit, termYears);
    }

    // ─── Debounced API trigger ─────────────────────────────────────────────────

    /**
     * Queues an API request with a 500 ms debounce to prevent excessive calls
     * during rapid slider movement.
     *
     * @param {number} exactDeposit Verified deposit amount.
     * @param {number} termYears    Repayment term in years.
     * @return {void}
     */
    function triggerApiCall(exactDeposit, termYears) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            fetchFinanceData(exactDeposit, termYears);
        }, 500);
    }

    // ─── API fetch ─────────────────────────────────────────────────────────────

    /**
     * Fetches quote details from the Auto Finance Online API.
     * Converts the frontend year value into the months integer required by the API.
     *
     * @param {number} deposit   Current deposit amount.
     * @param {number} termYears Repayment term in years.
     * @return {Promise<void>}
     */
    async function fetchFinanceData(deposit, termYears) {
        if (!afoConfig.apiKey) {
            console.error('AFO Calculator: API Key is missing from configuration.');
            return;
        }

        const baseUrl    = afoConfig.apiUrl;
        const termMonths = Math.round(parseFloat(termYears) * 12);

        const params = new URLSearchParams({
            vehicle_price: price,
            deposit:       parseFloat(deposit),
            term_length:   termMonths
        });

        try {
            const response = await fetch(baseUrl + '?' + params.toString(), {
                method:  'GET',
                headers: {
                    'X-API-Key':     afoConfig.apiKey,
                    'Content-Type':  'application/json'
                }
            });

            if (response.status === 429) {
                const retryAfter = response.headers.get('Retry-After') || 60;
                console.warn('AFO API Rate limit exceeded. Halting for ' + retryAfter + 's.');
                setTimeout(function () { fetchFinanceData(deposit, termYears); }, retryAfter * 1000);
                return;
            }

            if (!response.ok) {
                let errorMsg = 'HTTP ' + response.status + ' ' + response.statusText;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMsg = errorData.message;
                } catch (e) { /* swallow JSON parse errors on error bodies */ }
                throw new Error('AFO API Rejected Request: ' + errorMsg);
            }

            const result = await response.json();

            if (result.success && result.data && result.data.finance_options && result.data.finance_options.length > 0) {
                const options    = result.data.finance_options;
                let   bestOption = options.find(function (opt) {
                    return opt.type && opt.type.toLowerCase() === 'excellent';
                });

                if (!bestOption) bestOption = options[0];

                if (bestOption) {
                    const aprValue = bestOption.apr !== undefined ? bestOption.apr : '--';

                    // APR — show value only; the label in HTML already reads "APR"
                    const resRate = document.getElementById('afo-res-rate');
                    if (resRate) resRate.innerText = aprValue + '%';

                    const resCredit = document.getElementById('afo-res-credit');
                    if (resCredit) resCredit.innerText = fmtGBP(bestOption.cost_of_credit);

                    const resTotal = document.getElementById('afo-res-total');
                    if (resTotal) resTotal.innerText = fmtGBP(bestOption.total_repayable);

                    const resMonthly = document.getElementById('afo-res-monthly');
                    if (resMonthly) resMonthly.innerText = fmtGBP(bestOption.monthly_cost);
                }

                // Wire quote button to referrer link with current slider params appended
                if (result.data.referrer && result.data.referrer.link) {
                    const quoteBtn = document.getElementById('afo-quote-btn');
                    if (quoteBtn) {
                        quoteBtn.onclick = function () {
                            try {
                                const targetUrl    = new URL(result.data.referrer.link);
                                const currentBorrow  = document.getElementById('afo-borrow').value;
                                const currentDeposit = document.getElementById('afo-deposit').value;

                                targetUrl.searchParams.set('default-amount', currentBorrow);
                                targetUrl.searchParams.set('deposit', currentDeposit);
                                window.open(targetUrl.toString(), '_blank');
                            } catch (urlError) {
                                console.error('AFO Link Mutation Error. Falling back:', urlError);
                                window.open(result.data.referrer.link, '_blank');
                            }
                        };
                    }
                }
            } else {
                console.warn('AFO Calculator: API returned success but finance_options was empty.', result);
            }

        } catch (error) {
            console.error('AFO Finance API Transport Error:', error);
        }
    }

    // ─── Event listeners ───────────────────────────────────────────────────────

    depositSlider.addEventListener('input', syncSliders);
    borrowSlider.addEventListener('input',  syncSliders);
    termSlider.addEventListener('input',    syncSliders);

    // Recalculate bubble positions after a resize (offsetWidth changes)
    window.addEventListener('resize', function () {
        updateBubble(depositSlider, bubbleDeposit, fmtGBP);
        updateBubble(borrowSlider,  bubbleBorrow,  fmtGBP);
        updateBubble(termSlider,    bubbleTerm,    fmtYears);
    });

    // Bootstrap: fire once with no triggering element to initialise all displays
    syncSliders({ target: null });
}

// Ensure execution bypasses standard load-order restrictions
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAfoCalculator);
} else {
    initializeAfoCalculator();
}