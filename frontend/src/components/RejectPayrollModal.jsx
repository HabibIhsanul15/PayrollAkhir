import { useEffect, useId, useState } from "react";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function RejectPayrollModal({
  open,
  onClose,
  onConfirm,
  isSubmitting = false,
  errorMessage = "",
}) {
  const reasonId = useId();
  const [reason, setReason] = useState("");
  const [validationError, setValidationError] = useState("");

  useEffect(() => {
    if (!open) {
      setReason("");
      setValidationError("");
    }
  }, [open]);

  const handleOpenChange = (nextOpen) => {
    if (!nextOpen && !isSubmitting) onClose();
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    const trimmedReason = reason.trim();

    if (!trimmedReason) {
      setValidationError("Alasan penolakan wajib diisi.");
      return;
    }

    setValidationError("");
    onConfirm(trimmedReason);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent
        showCloseButton={false}
        className="max-w-md"
        onEscapeKeyDown={(event) => {
          if (isSubmitting) event.preventDefault();
        }}
        onPointerDownOutside={(event) => {
          if (isSubmitting) event.preventDefault();
        }}
      >
        <DialogHeader>
          <div className="mb-1 flex size-11 items-center justify-center rounded-full bg-rose-100 text-rose-600">
            <AlertTriangle size={22} aria-hidden="true" />
          </div>
          <DialogTitle>Tolak payroll?</DialogTitle>
          <DialogDescription>
            Payroll akan dikembalikan ke status draft agar dapat diperbaiki dan diajukan kembali.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-3">
          <div className="space-y-2">
            <Label htmlFor={reasonId}>Alasan penolakan</Label>
            <Input
              id={reasonId}
              value={reason}
              onChange={(event) => {
                setReason(event.target.value);
                if (validationError) setValidationError("");
              }}
              placeholder="Contoh: nominal tunjangan perlu diperiksa kembali"
              disabled={isSubmitting}
              autoFocus
              aria-invalid={Boolean(validationError || errorMessage)}
              aria-describedby={validationError || errorMessage ? `${reasonId}-error` : undefined}
            />
            {(validationError || errorMessage) && (
              <p id={`${reasonId}-error`} className="text-sm text-destructive">
                {validationError || errorMessage}
              </p>
            )}
          </div>

          <DialogFooter className="pt-2">
            <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
              Batal
            </Button>
            <Button type="submit" variant="destructive" disabled={isSubmitting}>
              {isSubmitting ? "Menolak..." : "Tolak Payroll"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
