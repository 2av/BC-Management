import { useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

type PasswordInputProps = React.ComponentProps<'input'> & {
  containerClassName?: string
}

export function PasswordInput({ className, containerClassName, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false)
  return (
    <div className={cn('relative', containerClassName)}>
      <Input
        {...props}
        type={visible ? 'text' : 'password'}
        className={cn('pr-10', className)}
      />
      <button
        type="button"
        tabIndex={-1}
        className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground hover:text-foreground"
        onClick={() => setVisible((v) => !v)}
        aria-label={visible ? 'Hide password' : 'Show password'}
      >
        {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
      </button>
    </div>
  )
}
