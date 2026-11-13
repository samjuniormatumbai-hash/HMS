<?php

?>
    </section>
  </main>

  <!-- FOOTER -->
  <footer class="bg-slate-900 text-slate-400 py-3 px-5 text-center text-xs border-t border-slate-800 no-print mt-auto">
    <span>AfyaSmart IHMS v2.4 &bull; Your Health, Our Priority </span>
  </footer>

  <!-- MEDICAL PRINT ENGINE & CLIENT SCRIPTS -->
  <script>
    /**
     * Bulletproof Thermal & A4 Receipt Print Engine
     * Uses invisible isolated iframe to completely bypass Chromium flex/overflow clipping.
     * Guaranteed to render receipt details on Microsoft Edge, Google Chrome, Firefox, and Windows Print Spooler.
     */
    function printReceipt() {
      var receiptEl = document.getElementById('printableReceipt');
      if (!receiptEl) {
        window.print();
        return;
      }

      var oldFrame = document.getElementById('__afyasmart_print_frame__');
      if (oldFrame) {
        oldFrame.parentNode.removeChild(oldFrame);
      }

      var iframe = document.createElement('iframe');
      iframe.id = '__afyasmart_print_frame__';
      iframe.style.position = 'fixed';
      iframe.style.right = '0';
      iframe.style.bottom = '0';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = '0';
      iframe.style.visibility = 'hidden';
      document.body.appendChild(iframe);

      var clone = receiptEl.cloneNode(true);
      var noPrintEls = clone.querySelectorAll('.no-print');
      for (var i = 0; i < noPrintEls.length; i++) {
        noPrintEls[i].remove();
      }

      var doc = iframe.contentWindow.document;
      doc.open();
      doc.write('<!DOCTYPE html><html><head><title>Official Medical Receipt</title>');
      doc.write('<style>');
      doc.write('@page { size: auto; margin: 6mm; }');
      doc.write('html, body { margin: 0; padding: 12px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #ffffff !important; color: #000000 !important; font-size: 13px; }');
      doc.write('.receipt-box { max-width: 400px; margin: 0 auto; border: 2px dashed #000000; padding: 20px; border-radius: 8px; background: #ffffff; }');
      doc.write('.text-center { text-align: center; }');
      doc.write('.uppercase { text-transform: uppercase; }');
      doc.write('.font-bold { font-weight: bold; }');
      doc.write('.font-black { font-weight: 900; }');
      doc.write('.font-mono { font-family: "Courier New", Courier, monospace; }');
      doc.write('.flex { display: flex; }');
      doc.write('.justify-between { justify-content: space-between; }');
      doc.write('.border-b { border-bottom: 1px dashed #666; padding-bottom: 8px; margin-bottom: 8px; }');
      doc.write('.border-t { border-top: 1px dashed #666; padding-top: 8px; margin-top: 8px; }');
      doc.write('.space-y-1 > * + * { margin-top: 4px; }');
      doc.write('.space-y-3 > * + * { margin-top: 10px; }');
      doc.write('.bg-slate-100 { background: #f1f5f9; padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1; }');
      doc.write('.text-emerald-700 { color: #047857 !important; font-weight: bold; }');
      doc.write('.text-slate-400 { color: #64748b; }');
      doc.write('.text-slate-500 { color: #475569; }');
      doc.write('.text-slate-800, .text-slate-900 { color: #000000; }');
      doc.write('</style></head><body>');
      doc.write('<div class="receipt-box">' + clone.innerHTML + '</div>');
      doc.write('</body></html>');
      doc.close();

      setTimeout(function() {
        try {
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
        } catch (e) {
          window.print();
        }
      }, 250);
    }
  </script>

</body>
</html>