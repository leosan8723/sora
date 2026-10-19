import { ApiError } from './api'

export type SetFieldError = (name: string, message: string) => void
/**
 * Map a validation/`ApiError` response onto a react-hook-form instance, or
 * surface the message as a form-level error.
 */
export function applyApiFieldErrors(
  error: unknown,
  setFieldError: SetFieldError,
  setFormMessage: (message: string) => void,
  fallback: string,
): void {
  if (error instanceof ApiError) {
    if (error.errors.length > 0) {
      for (const item of error.errors) {
        if (item.field) {
          setFieldError(item.field, item.message)
        }
      }
      return
    }
    setFormMessage(error.message)
    return
  }

  setFormMessage(fallback)
}
