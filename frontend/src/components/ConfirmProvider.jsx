import React, { createContext, useContext, useState, useCallback, useRef } from "react";
import { AlertTriangle, X, CheckCircle2, Info } from "lucide-react";
import { Button } from "@/components/ui/button";

const ConfirmContext = createContext(null);

export const useConfirm = () => {
  const context = useContext(ConfirmContext);
  if (!context) {
    throw new Error("useConfirm must be used within a ConfirmProvider");
  }
  return context;
};

export const ConfirmProvider = ({ children }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [message, setMessage] = useState("");
  const [title, setTitle] = useState("Konfirmasi");
  const [isAlert, setIsAlert] = useState(false);
  const [iconType, setIconType] = useState("warning");
  
  // Gunakan ref agar Promise resolver tidak hilang antar-render
  const resolver = useRef(null);

  const confirm = useCallback((msg, options = {}) => {
    setMessage(msg);
    setTitle(options.title || "Konfirmasi");
    setIsAlert(options.isAlert || false);
    setIconType(options.icon || "warning");
    setIsOpen(true);

    return new Promise((resolve) => {
      resolver.current = resolve;
    });
  }, []);

  const handleConfirm = () => {
    setIsOpen(false);
    if (resolver.current) resolver.current(true);
  };

  const handleCancel = () => {
    setIsOpen(false);
    if (resolver.current) resolver.current(false);
  };

  return (
    <ConfirmContext.Provider value={{ confirm }}>
      {children}
      
      {isOpen && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div 
            className="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden animate-in zoom-in-95 duration-200"
            role="dialog"
            aria-modal="true"
          >
            <div className="p-6">
              <div className={`w-12 h-12 rounded-full flex items-center justify-center mb-4 ${
                iconType === 'success' ? 'bg-emerald-100 text-emerald-600' :
                iconType === 'info' ? 'bg-blue-100 text-blue-600' :
                'bg-rose-100 text-rose-600'
              }`}>
                {iconType === 'success' ? <CheckCircle2 size={24} /> :
                 iconType === 'info' ? <Info size={24} /> :
                 <AlertTriangle size={24} />}
              </div>
              <h3 className="text-lg font-bold text-slate-800 mb-2">{title}</h3>
              <p className="text-sm text-slate-600 leading-relaxed">{message}</p>
            </div>
            
            <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end">
              {!isAlert && (
                <Button 
                  variant="outline" 
                  onClick={handleCancel}
                  className="bg-white hover:bg-slate-100 text-slate-700"
                >
                  Batal
                </Button>
              )}
              <Button 
                onClick={handleConfirm}
                className={
                  iconType === 'success' ? "bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm" :
                  iconType === 'info' ? "bg-blue-600 hover:bg-blue-700 text-white shadow-sm" :
                  "bg-rose-600 hover:bg-rose-700 text-white shadow-sm"
                }
              >
                {isAlert ? "Tutup" : "Ya, Lanjutkan"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  );
};
