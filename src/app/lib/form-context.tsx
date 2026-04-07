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
          setSteps(config);
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
    
    // Check all required fields in the step
    for (const field of stepConfig.fields) {
      if (field.required) {
        const value = data[field.name];
        
        // Check if field is empty
        if (value === undefined || value === null || value === '') {
          return false;
        }
        
        // Format validation based on field type
        if (field.type === 'email') {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(String(value))) {
            return false;
          }
        } else if (field.type === 'date') {
          const date = new Date(value);
          if (isNaN(date.getTime())) {
            return false;
          }
        } else if (field.type === 'number') {
          if (isNaN(Number(value))) {
            return false;
          }
        }
      }
      
      // Special validation for email confirmation
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
