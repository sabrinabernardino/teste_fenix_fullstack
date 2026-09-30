export const pct = (value) =>
  `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`

export const dateTime = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—')
