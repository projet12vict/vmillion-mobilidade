import type { CommandDef, OutputRef, ProcsRef, StreamLine } from "./types.js";
type UseProcessesOptions = {
    commandDefs: CommandDef[];
    cwd: string;
    bufferSize: number;
    streamBufferSize: number;
    timestamps: boolean;
    autoRestart: boolean;
    title?: string;
    columns: number;
    triggerRender: () => void;
    outputRef?: OutputRef;
    externalProcsRef?: ProcsRef;
};
export declare function useProcesses({ commandDefs, cwd, bufferSize, streamBufferSize, timestamps, autoRestart, title, columns, triggerRender, outputRef, externalProcsRef, }: UseProcessesOptions): {
    outputRowsRef: import("react").RefObject<string[][]>;
    outputPendingRef: import("react").RefObject<string[]>;
    streamLinesRef: import("react").RefObject<StreamLine[]>;
    failedProcs: Set<number>;
    restartProcess: (index: number) => void;
    clearOutput: (index: number) => void;
    clearStream: () => void;
    spawnTimeRef: import("react").RefObject<number[]>;
    pendingRestartsRef: import("react").RefObject<Set<number>>;
};
export {};
