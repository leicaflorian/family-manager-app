/**
 * What the sheet *It belongs to an expense* of *From the bank* says it chose: a due date of a planned item to pay
 * (`occurrence` is the date the rules give for it, never the day it falls) or a recorded movement to link.
 */
export type BankAssignChoice =
    | { kind: string; planned_id: number; occurrence: string }
    | { kind: string; movement_id: number };
