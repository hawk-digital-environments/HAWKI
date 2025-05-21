import * as process from 'node:process';
import {Application} from '@/Application.ts';

await (new Application()).run(process.argv);
