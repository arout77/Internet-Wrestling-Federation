import { showNotification, showConfirmation } from './ui-updater.js';
import { fetchWrestlerData, fetchMovesData, runSimulation } from './data.js';
import { createWrestlerCard, updateMatchCard, renderBulkResults } from './dom.js';

document.addEventListener('DOMContentLoaded', () => {
    // This is the main entry point for the application's JavaScript.
    // It should initialize any page-specific logic.

    // Example: If we are on the simulator page, initialize it.
    if (document.getElementById('roster-container')) {
        // The simulator.js module will handle its own initialization.
    }

    // You can add other page initializations here as the app grows.
});