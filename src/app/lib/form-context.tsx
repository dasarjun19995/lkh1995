"use client";

import React, { createContext, useContext, useState, useEffect, ReactNode } from "react";
import { PERSONAL_STEPS, BUSINESS_STEPS } from "./form-steps";

export type ApplicationType = "personal" | "business";

export type ApplicationData = {
  type: ApplicationType;
  accountTypeId: string;
  applicationId: string;
  attestation: {
    agreedToTerms: boolean;
    signatureName: string;
    signatureDate: string;
    idNumber: string;
    signatureImage?: string;
  };
  [key: string]: any;
};

type FormContextType = {
  currentStep: number;
  totalSteps: number;
  data: ApplicationData;
  steps: any[];
  isLoading: boolean;
  updateData: (newData: Partial<ApplicationData>) => void;
  nextStep: () => void;
  prevStep: () => void;
  setStep: (step: number) => void;
  setType: (type: ApplicationType) => void;
  canContinue: (step?: number) => boolean;
};

const defaultData: ApplicationData = {
  type: "personal",
  accountTypeId: "",
  applicationId: crypto.randomUUID().substring(0, 8),
  attestation: {
    agreedToTerms: false,
    signatureName: "",
    signatureDate: "",
    idNumber: "",
    signatureImage: "",
  },
};

const isValidEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

const calculateAge = (birthDate: string): number => {
  const today = new Date();
  const birth = new Date(birthDate);
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--;
  }
  return age;
};

const normalizeFetchedSteps = (remoteSteps: any[], localSteps: any[]) => {
  const localFieldMap = new Map<string, any>();
  localSteps.forEach((step) => {
    step.fields?.forEach((field: any) => {
      if (field?.name) {
        localFieldMap.set(field.name, field);
      }
    });
  });

  return remoteSteps.map((step) => ({
    ...step,
    title: step.title || localSteps.find((localStep) => localStep.id === step.id)?.title,
    fields: Array.isArray(step.fields)
      ? step.fields.map((field: any) => {
          const localField = localFieldMap.get(field.name);
          if (!localField) return field;

          return {
            ...field,
            label: localField.label ?? field.label,
            type: localField.type ?? field.type,
            required: localField.required ?? field.required,
            width: localField.width ?? field.width,
            numericOnly: localField.numericOnly ?? field.numericOnly,
            minAge: localField.minAge ?? field.minAge,
            readOnly: localField.readOnly ?? field.readOnly,
            autoPopulateCurrentDate: localField.autoPopulateCurrentDate ?? field.autoPopulateCurrentDate,
            validation: {
              ...field.validation,
              ...localField.validation,
            },
            options: field.options?.length ? field.options : localField.options,
          };
        })
      : step.fields,
  }));
};

const FormContext = createContext<FormContextType | undefined>(undefined);

export function FormProvider({ children }: { children: ReactNode }) {
  const [currentStep, setCurrentStep] = useState(1);
  const [data, setData] = useState<ApplicationData>(defaultData);
  const [steps, setSteps] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  
  useEffect(() => {
    async function fetchSteps() {
      setIsLoading(true);
      const defaultSteps = data.type === 'personal' ? PERSONAL_STEPS : BUSINESS_STEPS;
      
      try {
        const isLocalDevelopment = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || process.env.NODE_ENV === 'development');
        if (isLocalDevelopment) {
          setSteps(defaultSteps);
          setIsLoading(false);
          return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000);

        const runtimeApiUrl = typeof window !== 'undefined' ? window.location.origin : '';
        const apiUrl = process.env.NEXT_PUBLIC_FAAP_API_URL || runtimeApiUrl || "http://3.14.204.157";
        const response = await fetch(`${apiUrl.replace(/\/$/, '')}/wp-json/faap/v1/form-config/${data.type}`, {
          signal: controller.signal,
          headers: { 'Accept': 'application/json' },
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
          const text = await response.text();
          throw new Error(`Server error ${response.status}: ${text.slice(0, 240)}`);
        }
        const raw = await response.text();
        let config;
        try {
          config = JSON.parse(raw);
        } catch {
          config = null;
        }

        if (Array.isArray(config) && config.length > 0 && config.every(step => step.order && step.fields)) {
          setSteps(normalizeFetchedSteps(config, defaultSteps));
        } else {
          setSteps(defaultSteps);
        }
      } catch (e) {
        setSteps(defaultSteps);
      } finally {
        setIsLoading(false);
      }
    }
    fetchSteps();
  }, [data.type]);

  const totalSteps = (steps?.length ?? 0) + 1;

  // Auto-advance to step 2 when account type is selected
  useEffect(() => {
    if (data.accountTypeId && currentStep === 1) {
      setCurrentStep(2);
    }
  }, [data.accountTypeId]);

  const updateData = (newData: Partial<ApplicationData>) => {
    setData((prev) => ({
      ...prev,
      ...newData,
      attestation: newData.attestation ? { ...prev.attestation, ...newData.attestation } : prev.attestation
    }));
  };

  const canContinue = (step?: number) => {
    const stepToCheck = step ?? currentStep;
    
    // Step 1: Account type selection
    if (stepToCheck === 1) {
      return !!data.accountTypeId;
    }
    
    // Step 8: Payment/Document uploads - special validation
    if (stepToCheck === 8) {
      const isPersonal = data.type === 'personal';
      if (isPersonal) {
        // Personal: requires passport photo and payment proof
        return !!(data.passportPhotoFile && data.paymentProofFile);
      } else {
        // Business: requires company registration file and payment proof
        return !!(data.companyRegFile && data.paymentProofFile);
      }
    }
    
    // Step 9: Review & Attestation - special validation
    if (stepToCheck === 9) {
      const isPersonal = data.type === 'personal';
      if (isPersonal) {
        // Personal: requires attestation checkbox, name, date, and signature
        return !!(
          data.attestation?.agreedToTerms &&
          data.attestation?.signatureName &&
          data.attestation?.signatureDate &&
          data.attestation?.signatureImage
        );
      } else {
        // Business: requires agreement checkbox, full name, date, and signature
        return !!(
          data.agree &&
          data.fullName &&
          data.date &&
          data.signature
        );
      }
    }
    
    // Get the step configuration
    const stepConfig = steps.find(s => s.order === stepToCheck);
    if (!stepConfig || !stepConfig.fields) {
      return true; // Allow if no fields defined
    }

    const isValidFieldValue = (field: any, value: any): boolean => {
      const trimmedValue = typeof value === 'string' ? value.trim() : value;
      if (field.required && (trimmedValue === undefined || trimmedValue === null || trimmedValue === '')) {
        return false;
      }

      if (!trimmedValue) {
        return true;
      }

      if (field.type === 'email' && !isValidEmail(String(trimmedValue))) {
        return false;
      }

      if (field.type === 'date') {
        const selectedDate = new Date(String(trimmedValue));
        if (isNaN(selectedDate.getTime())) {
          return false;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);

        if (field.validation?.notFutureDates && selectedDate > today) {
          return false;
        }

        if (field.validation?.notExpiredDates && selectedDate < today) {
          return false;
        }

        if (field.minAge && field.name === 'dob') {
          if (calculateAge(String(trimmedValue)) < field.minAge) {
            return false;
          }
        }

        if (field.validation?.compareField) {
          const compareValue = data[field.validation.compareField.fieldName];
          if (compareValue) {
            const compareDate = new Date(String(compareValue));
            compareDate.setHours(0, 0, 0, 0);
            if (field.validation.compareField.operator === 'before' && selectedDate >= compareDate) {
              return false;
            }
            if (field.validation.compareField.operator === 'after' && selectedDate <= compareDate) {
              return false;
            }
          }
        }
      }

      if (field.numericOnly && typeof trimmedValue === 'string') {
        const numericPattern = /^[0-9+\-().\s]*$/;
        if (!numericPattern.test(trimmedValue)) {
          return false;
        }
      }

      if (field.type === 'number' && trimmedValue !== '' && isNaN(Number(trimmedValue))) {
        return false;
      }

      return true;
    };

    for (const field of stepConfig.fields) {
      if (!isValidFieldValue(field, data[field.name])) {
        return false;
      }

      if (field.name === 'emailConfirm' && data.email !== data.emailConfirm) {
        return false;
      }
      if (field.name === 'signatoryEmailConfirm' && data.signatoryEmail !== data.signatoryEmailConfirm) {
        return false;
      }
    }

    return true;
  };

  const nextStep = () => {
    if (canContinue()) {
      setCurrentStep((prev) => Math.min(prev + 1, totalSteps));
    }
  };
  const prevStep = () => setCurrentStep((prev) => Math.max(prev - 1, 1));
  const setStep = (step: number) => setCurrentStep(step);
  const setType = (type: ApplicationType) => {
    setData({ ...defaultData, type, applicationId: crypto.randomUUID().substring(0, 8) });
    setCurrentStep(1);
  };

  return (
    <FormContext.Provider
      value={{
        currentStep,
        totalSteps,
        data,
        steps,
        isLoading,
        updateData,
        nextStep,
        prevStep,
        setStep,
        setType,
        canContinue,
      }}
    >
      {children}
    </FormContext.Provider>
  );
}

export function useForm() {
  const context = useContext(FormContext);
  if (context === undefined) {
    throw new Error("useForm must be used within a FormProvider");
  }
  return context;
}
