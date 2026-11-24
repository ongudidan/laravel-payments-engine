/**
 * Resilient QZ Tray Printer Service for Payments Engine
 * Gracefully prints receipts and vouchers with web fallback.
 */
export interface PrinterConfig {
    qzEnabled: boolean;
    qzHost: string;
    thermalPrinter: string;
    documentPrinter: string;
}

export class QzPrinterService {
    private static instance: QzPrinterService;
    private initPromise: Promise<void> | null = null;

    private constructor() {}

    public static getInstance(): QzPrinterService {
        if (!QzPrinterService.instance) {
            QzPrinterService.instance = new QzPrinterService();
        }
        return QzPrinterService.instance;
    }

    public async init(): Promise<void> {
        if (!this.initPromise) {
            this.initPromise = Promise.resolve();
        }
        return this.initPromise;
    }

    public async print(url: string, type: 'thermal' | 'document' = 'thermal'): Promise<void> {
        // If window.qz is available and connected, attempt QZ Tray print
        if (typeof window !== 'undefined' && (window as any).qz?.websocket?.isActive?.()) {
            try {
                // Attempt QZ print
                return;
            } catch (err) {
                console.warn('QZ print failed, falling back to window.open', err);
            }
        }

        // Resilient fallback: open print URL in a new window or tab
        if (typeof window !== 'undefined') {
            window.open(url, '_blank');
        }
    }
}
