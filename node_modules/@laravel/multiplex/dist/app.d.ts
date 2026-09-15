import type { CommandDef, OutputRef, ProcsRef } from "./types.js";
type AppProps = {
    commandDefs: CommandDef[];
    cwd: string;
    initialStreamMode: boolean;
    bufferSize?: number;
    streamBufferSize?: number;
    timestamps?: boolean;
    autoRestart?: boolean;
    title?: string;
    outputRef?: OutputRef;
    procsRef?: ProcsRef;
};
export declare function App({ commandDefs, cwd, initialStreamMode, bufferSize, streamBufferSize, timestamps, autoRestart, title, outputRef, procsRef: externalProcsRef, }: AppProps): import("react").JSX.Element;
export {};
